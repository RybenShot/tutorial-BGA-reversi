<?php
declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll;

use Bga\Games\TutorialHorrorRoll\States\PlayDisc;
use Bga\GameFramework\Components\Counters\PlayerCounter;

// Fijate que extiende de una parte del Framework de BGA, en este caso el tablero, en el caso de los estados extiende e GameState, para controlar los estados del juego
class Game extends \Bga\GameFramework\Table{
 
    public PlayerCounter $playerEnergy;

    public function __construct(){
        parent::__construct();
        $this->initGameStateLabels([]);
    }

    /**
     * Configura una nueva partida.
     * Se ejecuta UNA VEZ al crear la partida.
     * 
     * @param array $players Array de jugadores con sus datos
     * @param array $options Opciones de la partida
     * @return string Clase del primer estado del juego
     */
    protected function setupNewGame($players, $options = []){

        //! 1- Creamos los jugadores en BD
        $default_color = array( "000000", "ffffff" );
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_color );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";

            if( $color == '000000' ) $blackplayer_id = $player_id;
            else $whiteplayer_id = $player_id;
        }
        $sql .= implode( ',', $values );
        $this->DbQuery( $sql );
        $this->reloadPlayersBasicInfos();

        // $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        //! 2- Llenamos el tablero con 64 casillas vacias y 4 fichas iniciales
        $sql = "INSERT INTO board (board_x,board_y,board_player) VALUES ";
        $sql_values = [];
        $board_size = 8;
        $board_size_half = 4;
        list( $blackplayer_id, $whiteplayer_id ) = array_keys( $players );
        for( $x = 1; $x <= $board_size; $x++ ) {
            for( $y = 1; $y <= $board_size; $y++) {
                $disc_value = "NULL";
                // Posiciones iniciales del jugador blanco (diagonal)
                if( ($x == $board_size_half && $y == $board_size_half) || ($x == ($board_size_half + 1) && $y == ($board_size_half + 1)) )
                    $disc_value = "'$whiteplayer_id'";
                // Posiciones iniciales del jugador negro (diagonal contraria)
                else if( ($x == $board_size_half && $y == ($board_size_half + 1)) || ($x == ($board_size_half + 1) && $y == $board_size_half) )
                    $disc_value = "'$blackplayer_id'";

                $sql_values[] = "('$x','$y',$disc_value)";
            }
        }
        $sql .= implode( ',', $sql_values );
        $this->DbQuery( $sql );

        // Inicializar estadísticas
        $this->initStat( 'player', 'discPlayedOnCorner', 0 );
        $this->initStat( 'player', 'discPlayedOnBorder', 0 );
        $this->initStat( 'player', 'discPlayedOnCenter', 0 );
        $this->initStat( 'player', 'turnedOver', 0 );

        // Puntuación inicial (2 fichas por jugador)
        $score = $this->isReverseReversi() ? -2 : 2;
        $this->playerScore->setAll($score, null);

        //! 3- Activamos el primer jugador
        $this->activeNextPlayer();

        //! 4- Comenzamos el juego con el estado "PlayerTurn"
        return PlayDisc::class;
    }

    // captura todos los datos del juego para enviar al cliente
    protected function getAllDatas(): array {
        $result = [];

        // Obtener información de los jugadores
        $result["players"] = self::getCollectionFromDb( 
            "SELECT `player_id` `id`, `player_score` `score`, `player_color` `color` FROM `player`"
        );

        // Obtener fichas actuales en el tablero
        $result['board'] = $this->getObjectListFromDB( 
            "SELECT board_x x, board_y y, board_player player FROM board WHERE board_player IS NOT NULL" 
        );

        $result['board_size'] = 8;
        
        return $result;
    }

    function getBoardSize(): int { 
        return 8; 
    }

    public function getGameProgression() { 
        return 0; 
    }

    public function upgradeTableDb($from_version){}

    public function debug_goToState(int $state = 3) { 
        $this->gamestate->jumpToState($state); 
    }

    /**
     * Calcula qué fichas se voltearían si el jugador coloca una ficha en la posición (x, y)
     * Devuelve un array con las coordenadas de las fichas que se voltearían, o array vacío si es movimiento inválido
     * 
     * @param int $x Coordenada X donde se quiere colocar la ficha
     * @param int $y Coordenada Y donde se quiere colocar la ficha
     * @param int $player ID del jugador que quiere colocar la ficha
     * @param array $board Tablero completo (array 2D con todas las casillas)
     * @return array Array de coordenadas ['x' => x, 'y' => y] de fichas que se voltearían
     */
    function getTurnedOverDiscs(int $x, int $y, int $player, array $board): array {
        
        $turnedOverDiscs = array(); // Fichas que se voltearán al final

        // Si la casilla ya está ocupada, no es un movimiento válido
        if ($board[$x][$y] === null) {
            
            // Las 8 direcciones posibles: ↖ ↑ ↗ ← → ↙ ↓ ↘
            $directions = array(
                array(-1, -1), array(-1, 0), array(-1, 1), array(0, -1),
                array(0, 1),   array(1, -1), array(1, 0),  array(1, 1)
            );

            // Revisar cada dirección
            foreach ($directions as $direction) {
                
                // Empezamos desde la casilla donde queremos colocar la ficha
                $current_x = $x;
                $current_y = $y;
                $bContinue = true;
                $mayBeTurnedOver = []; // Fichas que PODRÍAN voltearse en esta dirección

                // Avanzar en esta dirección hasta encontrar algo definitivo
                while ($bContinue) {
                    
                    // Mover a la siguiente casilla en esta dirección
                    $current_x += $direction[0];
                    $current_y += $direction[1];

                    // ¿Nos salimos del tablero?
                    if ($current_x < 1 || $current_x > 8 || $current_y < 1 || $current_y > 8)
                        $bContinue = false; // Fuera del tablero → parar en esta dirección
                    // ¿Es una casilla vacía?
                    else if ($board[$current_x][$current_y] === null)
                        $bContinue = false; // Casilla vacía → parar en esta dirección
                    // ¿Es una ficha del oponente?
                    else if ($board[$current_x][$current_y] != $player) {
                        // Ficha del oponente → añadirla a las "que podrían voltearse"
                        // y continuar avanzando en esta dirección
                        $mayBeTurnedOver[] = array('x' => $current_x, 'y' => $current_y);
                    }
                    
                    // ¿Es una ficha nuestra?
                    else if ($board[$current_x][$current_y] == $player) {
                        
                        // Si NO hay fichas del oponente entre medias
                        if (count($mayBeTurnedOver) == 0) {
                            // No hay nada que voltear en esta dirección → parar
                            $bContinue = false;
                        }
                        // Si SÍ hay fichas del oponente entre medias
                        else {
                            // ¡Encontramos fichas para voltear!
                            // Añadirlas al resultado final y parar en esta dirección
                            $turnedOverDiscs = array_merge($turnedOverDiscs, $mayBeTurnedOver);
                            $bContinue = false;
                        }
                    }
                }
            }
        }

        return $turnedOverDiscs;
    }

    // Lee TODA la tabla board de la BD
    function getBoard() {
        return self::getDoubleKeyCollectionFromDB( "SELECT board_x x, board_y y, board_player player FROM board", true );
    }

    // retorna array con todas las casillas válidas para ese jugador
    function getPossibleMoves( $player_id ) {
        $result = [];

        $board = self::getBoard();

        // Recorremos las 64 casillas
        for( $x=1; $x<=8; $x++ ){
            for( $y=1; $y<=8; $y++ ){
                // para cada una llamamos a la funcion "Oraculo" (revisa la ficha , busca y muestra posibles movimientos al jugador actual )
                $returned = self::getTurnedOverDiscs( $x, $y, $player_id, $board );
                if( count( $returned ) == 0 ){
                    // si no voltea una ficha, NO es un movimientos valido
                } else {
                    // si voltea una ficha, SI es un movimientos valido
                    if( ! isset( $result[$x] ) )
                        $result[$x] = [];

                    $result[$x][$y] = true;
                }
            }
        }
        return $result;
    }

    function isReverseReversi(): bool {
        return false;
    }

    function getFormattedCoordinates(int $x, int $y): string {
        return chr(64 + $x) . $y;
    }

}
