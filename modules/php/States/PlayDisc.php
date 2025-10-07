<?php

declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\TutorialHorrorRoll\Game;
use Bga\Games\TutorialHorrorRoll\States\NextPlayer;

// Turno del jugador activo para colocar un disco
class PlayDisc extends GameState
{
    function __construct( protected Game $game) {
        parent::__construct($game,
            id: 2,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} Que vas a hacer?'),
            descriptionMyTurn: clienttranslate('${you} Que vas a hacer?'),
        );
    }

    // Obtiene los argumentos del estado para enviar al cliente
    function getArgs(int $activePlayerId): array {
         return [ 'possibleMoves' => $this->game->getPossibleMoves($activePlayerId) ];
    }

    /**
     * Acción: El jugador coloca un disco en la posición (x, y).
     * Valida el movimiento, actualiza el tablero, envía notificaciones y actualiza puntuaciones.
     * 
     * @param int $x Coordenada X donde se coloca el disco
     * @param int $y Coordenada Y donde se coloca el disco
     * @param int $activePlayerId ID del jugador que realiza la acción
     * @param array $args Argumentos del estado (movimientos posibles)
     * @return string Clase del siguiente estado (NextPlayer)
     */
    #[PossibleAction]
    function actPlayDisc(int $x, int $y, int $activePlayerId, array $args) {        
        // Validar que el movimiento sea posible
        if (!array_key_exists($x, $args['possibleMoves']) || !array_key_exists($y, $args['possibleMoves'][$x])) {
            throw new \BgaUserException(clienttranslate("Impossible move"));
        }

        $board = $this->game->getBoard();
        $board_size = $this->game->getBoardSize();
        $turnedOverDiscs = $this->game->getTurnedOverDiscs( $x, $y, $activePlayerId, $board );
        
        // Doble verificación: el movimiento debe voltear al menos una ficha
        if( count( $turnedOverDiscs ) === 0 ) {
            throw new \BgaSystemException(clienttranslate("Impossible move"));
        }
            
        // Actualizar la base de datos: colocar el disco y voltear las fichas capturadas
        $sql = "UPDATE board SET board_player='$activePlayerId'
                WHERE ( board_x, board_y) IN ( ";
        
        foreach( $turnedOverDiscs as $turnedOver ) {
            $sql .= "('".$turnedOver['x']."','".$turnedOver['y']."'),";
        }
        $sql .= "('$x','$y') ) ";
                    
        $this->game->DbQuery( $sql );
        
        // Actualizar estadísticas del jugador
        $disc_count = count( $turnedOverDiscs );
        $this->game->incStat( $disc_count, "turnedOver", $activePlayerId );

        // Estadística según posición: esquina, borde o centro
        if( ($x==1 && $y==1) || ($x==$board_size && $y==1) || ($x==1 && $y==$board_size) || ($x==$board_size && $y==$board_size) ) {
            $this->game->incStat( 1, 'discPlayedOnCorner', $activePlayerId );
        } else if( $x==1 || $x==$board_size || $y==1 || $y==$board_size ) {
            $this->game->incStat( 1, 'discPlayedOnBorder', $activePlayerId );
        } else {
            $this->game->incStat( 1, 'discPlayedOnCenter', $activePlayerId );
        }
        
        // Notificar a todos los jugadores: disco seleccionado por el jugador colocado
        $this->notify->all( "playDisc", $disc_count == 1 ? clienttranslate( '${player_name} plays a disc on ${coordinates} and turns over 1 disc' ) : clienttranslate( '${player_name} plays a disc on ${coordinates} and turns over ${returned_nbr} discs' ), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'coordinates' => $this->game->getFormattedCoordinates($x, $y),
            'returned_nbr' => $disc_count,
            'x' => $x,
            'y' => $y,
        ]);

        // Notificar a todos los jugadores: resto de discos volteados
        $this->notify->all( "turnOverDiscs", '', [
            'player_id' => $activePlayerId,
            'turnedOver' => $turnedOverDiscs,
        ]);
        
        // Actualizar puntuaciones de todos los jugadores según discos en tablero
        $playerIds = array_keys($this->game->loadPlayersBasicInfos());
        foreach ($playerIds as $playerId) {
            $tokens = (int)$this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM `board` WHERE `board_player` = $playerId");
            $score = $this->game->isReverseReversi() ? -$tokens : $tokens;
            $this->playerScore->set($playerId, $score);
        }
        
        // Transición al siguiente estado
        return NextPlayer::class;
    }

    /**
     * Modo Zombie: Gestiona el turno de un jugador que abandonó la partida.
     * Selecciona un movimiento aleatorio entre los posibles.
     * 
     * @param int $playerId ID del jugador zombie
     * @return string Clase del siguiente estado
     */
    function zombie(int $playerId) {
        // Obtener todos los movimientos posibles
        $possibleMoves = $this->game->getPossibleMoves($playerId);
        $possibleMovesArray = [];
        foreach($possibleMoves as $x => $ys) {
            foreach($ys as $y => $valid) {
                $possibleMovesArray[] = [$x, $y];
            }
        }

        // Seleccionar un movimiento aleatorio
        $zombieChoice = $this->getRandomZombieChoice($possibleMovesArray);
        return $this->actPlayDisc($zombieChoice[0], $zombieChoice[1], $playerId, $this->getArgs($playerId));
    }
}