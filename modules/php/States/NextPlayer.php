<?php

declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\TutorialHorrorRoll\Game;
use Bga\Games\TutorialHorrorRoll\States\PlayDisc;

// Estado Transición entre turnos
class NextPlayer extends GameState {

    const ST_END_GAME = 99;

    function __construct( protected Game $game ) {
        parent::__construct($game,
            id: 3,
            type: StateType::GAME,

            updateGameProgression: true,
        );
    }

    /**
     * Se ejecuta automáticamente cuando el juego entra en el estado "NextPlayer"
     * Es el estado de transición entre turnos de jugadores
     * Determina si el juego continúa, termina, o si un jugador debe pasar turno
     */
    function onEnteringState() {
        // Activar el siguiente jugador en el orden natural
        $player_id = intval($this->game->activeNextPlayer());

        // Contar fichas por jugador y casillas libres
        $player_to_discs = $this->game->getCollectionFromDb( "SELECT board_player, COUNT( board_x )
                                                       FROM board
                                                       GROUP BY board_player", true );

        // Verificar si el tablero está completo
        if( ! isset( $player_to_discs[ null ] ) ) {
            // No hay casillas libres => fin del juego
            return ST_END_GAME;
        } else if( ! isset( $player_to_discs[ $player_id ] ) ) {    // Verificar si el jugador activo tiene fichas en el tablero
            // El jugador activo no tiene fichas => pierde inmediatamente
            return ST_END_GAME;
        }
        
        // Verificar si el jugador actual puede realizar algún movimiento
        $possibleMoves = $this->game->getPossibleMoves( $player_id );
        if( count( $possibleMoves ) == 0 ) {

            // El jugador actual no puede mover? verificamos si el oponente puede mover
            $opponent_id = intval($this->game->getUniqueValueFromDb( "SELECT player_id FROM player WHERE player_id!='$player_id' " ));
            if( count( $this->game->getPossibleMoves( $opponent_id ) ) == 0 ) {
                // Ningún jugador puede mover => fin del juego
                return ST_END_GAME;
            } else {            
                // El oponente sí puede mover => pasar el turno
                return NextPlayer::class;
            }
        } else {
            // El jugador puede mover => darle tiempo extra y continuar
            $this->game->giveExtraTime( $player_id );
            
            return PlayDisc::class;
        }
    }
}