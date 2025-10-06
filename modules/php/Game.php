<?php
declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll;

use Bga\Games\TutorialHorrorRoll\States\PlayerTurn;
use Bga\GameFramework\Components\Counters\PlayerCounter;

// Fijate que extiende de una parte del Framework de BGA, en este caso el tablero, en el caso de los estados extiende e GameState, para controlar los estados del juego
class Game extends \Bga\GameFramework\Table{
 
    public PlayerCounter $playerEnergy;

    public function __construct(){

        parent::__construct();
        $this->initGameStateLabels([]); // mandatory, even if the array is empty

        // Inicializamos el contador de energía de jugadores
        $this->playerEnergy = $this->counterFactory->createPlayerCounter('energy');
    }

    // Envia datos al cliente cuando carga el juego
    protected function getAllDatas(): array {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score`, `player_color` `color` FROM `player`"
        );
        $this->playerEnergy->fillResult($result);

        // Get reversi board token
        $result['board'] = self::getObjectListFromDB( "SELECT board_x x, board_y y, board_player player
            FROM board
            WHERE board_player IS NOT NULL" );

        return $result; // Estos datos llegan al JavaScript como datosDelJuego
    }

    // Se ejecuta UNA VEZ al crear la partida
    protected function setupNewGame($players, $options = []){

        //! 1- Creamos los jugadores en BD
        $this->playerEnergy->initDb(array_keys($players), initialValue: 2);

        $gameinfos = $this->getGameinfos();
        $default_colors = array("ffffff", "000000");

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        // $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        //! 2- Llenamos el tablero con 64 casillas vacias y 4 fichas iniciales
        $sql = "INSERT INTO board (board_x,board_y,board_player) VALUES ";
        $sql_values = array();
        list( $blackplayer_id, $whiteplayer_id ) = array_keys( $players );
        for( $x=1; $x<=8; $x++ ){
            for( $y=1; $y<=8; $y++ ){
                $token_value = "NULL";

                if( ($x==4 && $y==4) || ($x==5 && $y==5) ){ // Initial positions of white player
                    $token_value = "'$whiteplayer_id'";
                } else if( ($x==4 && $y==5) || ($x==5 && $y==4) ) { // Initial positions of black player
                    $token_value = "'$blackplayer_id'";
                }                
                $sql_values[] = "('$x','$y',$token_value)";
            }
        }
        $sql .= implode( ',', $sql_values );
        $this->DbQuery( $sql );

        //! 3- Activamos el primer jugador
        $this->activeNextPlayer();

        //! 4- Comenzamos el juego con el estado "PlayerTurn"
        return PlayerTurn::class;
    }

    public function getGameProgression() { return 0; }
    public function upgradeTableDb($from_version){ }
    public function debug_goToState(int $state = 3) { $this->gamestate->jumpToState($state); }

    public function debug_playAutomatically(int $moves = 50) {
        $count = 0;
        while (intval($this->gamestate->getCurrentMainStateId()) < 99 && $count < $moves) {
            $count++;
            foreach($this->gamestate->getActivePlayerList() as $playerId) {
                $playerId = (int)$playerId;
                $this->gamestate->runStateClassZombie($this->gamestate->getCurrentState($playerId), $playerId);
            }
        }
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
    function getTurnedOverDiscs($x, $y, $player, $board) {
        
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
                $mayBeTurnedOver = array(); // Fichas que PODRÍAN voltearse en esta dirección

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

    // Devuelve array con todas las casillas válidas para ese jugador
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
                        $result[$x] = array();

                    $result[$x][$y] = true;
                }
            }
        }
        return $result;
    }

}
