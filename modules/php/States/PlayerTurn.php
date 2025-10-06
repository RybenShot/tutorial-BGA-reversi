<?php

declare(strict_types=1);

namespace Bga\Games\TutorialHorrorRoll\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\TutorialHorrorRoll\Game;

// Estado "tu turno"
class PlayerTurn extends GameState{

    function __construct( protected Game $game,) {
        parent::__construct($game,
            id: 10,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} Que vas a hacer?'),
            descriptionMyTurn: clienttranslate('${you} Que vas a hacer?'),
        );
    }

    function getArgs(int $activePlayerId): array {
        // Retorna ['possibleMoves' => ...] → esto llega al JavaScript como args.args.possibleMoves
        return [ 'possibleMoves' => $this->game->getPossibleMoves($activePlayerId) ];
    }

    #[PossibleAction]
    function actPlayCard(int $card_id, int $activePlayerId, array $args) {

        $playableCardsIds = $args['playableCardsIds'];
        if (!in_array($card_id, $playableCardsIds)) {
            throw new \BgaUserException('Invalid card choice');
        }

        $card_name = Game::$CARD_TYPES[$card_id]['card_name'];

        $this->notify->all("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
            "player_id" => $activePlayerId,
            "player_name" => $this->game->getPlayerNameById($activePlayerId),
            "card_name" => $card_name,
            "card_id" => $card_id,
            "i18n" => ['card_name'],
        ]);

        $this->playerScore->inc($activePlayerId, 1);

        return NextPlayer::class;
    }

    #[PossibleAction]
    function actPass(int $activePlayerId){

        $this->notify->all("pass", clienttranslate('${player_name} passes'), [
            "player_id" => $activePlayerId,
            "player_name" => $this->game->getPlayerNameById($activePlayerId),
        ]);

        $this->game->playerEnergy->inc($activePlayerId, 1);

        return NextPlayer::class;
    }

    function zombie(int $playerId) {
        $args = $this->getArgs();
        $zombieChoice = array_rand(array_flip($args['playableCardsIds']));
        return $this->actPlayCard($zombieChoice, $playerId, $args);
    }
}