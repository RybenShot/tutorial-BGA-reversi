<?php

declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\TutorialHorrorRoll\Game;

// Estado Transición entre turnos
class NextPlayer extends GameState
{

    function __construct( protected Game $game ) {
        parent::__construct($game,
            id: 90,
            type: StateType::GAME
        );
    }

    /**
     * Se ejecuta automáticamente cuando el juego entra en el estado "NextPlayer"
     * Es el estado de transición entre turnos de jugadores
     * 
     * @param int $activePlayerId ID del jugador que acaba de completar su turno
     */
    function onEnteringState(int $activePlayerId) {

        // Da tiempo extra al jugador activo (recompensa por haber jugado)
        $this->game->giveExtraTime($activePlayerId);
        
        // Cambia al siguiente jugador en el orden de turnos
        $this->game->activeNextPlayer();

        // Decide a qué estado ir después
        $gameEnd = false; // TODO: Aquí deberías detectar si el juego ha terminado
        
        if ($gameEnd) {
            // Si el juego terminó → ir al estado de puntuación final
            return EndScore::class;
        } else {
            // Si el juego continúa → volver al estado donde el siguiente jugador juega
            return PlayerTurn::class;
        }
    }
}