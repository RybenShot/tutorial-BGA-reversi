define([
    "dojo", "dojo/_base/declare", 
    "ebg/core/gamegui", 
    "ebg/counter",
    getLibUrl('bga-animations', '1.x')
],

function (e, declare, n, a, BgaAnimations) {
    return declare("bgagame.tutorialhorrorroll", ebg.core.gamegui, {

        // variable para probar que funcione bien la puesta de fichas
        //TODO una vez puesta a PROD borrar esta variable
        localizacionFicha : 'square_2_2',

        // constructor actualmente sin nada
        constructor: function() { 
            console.log('Bienvenido a reversi, mi tutorial'); 
        },

        // aqui construimos la interfaz inicial (solo se ejecuta 1 vez, al iniciar), Constructor de la interfaz
        setup: function(datosDelJuego) {
            console.warn('=== INICIO SETUP!! ===');
            

            this.animationManager = new BgaAnimations.Manager({
                animationsActive: () => this.bgaAnimationsActive(),
            });

            // Creamos el board
            this.getGameAreaElement().insertAdjacentHTML("beforeend", '\n <div id="board"></div>\n ');

            const board = document.getElementById('board');
            // console.log('Board encontrado:', board);

            const hor_scale = 64.8;
            const ver_scale = 64.4;

            for (let x=1; x<=8; x++) {
                for (let y=1; y<=8; y++) {
                    const left = Math.round((x - 1) * hor_scale + 10);
                    const top = Math.round((y - 1) * ver_scale + 7);
                    // we use afterbegin to make sure squares are placed before discs
                    board.insertAdjacentHTML(`afterbegin`, `<div id="square_${x}_${y}" class="square" style="left: ${left}px; top: ${top}px;"></div>`);
                }
            }

            // Setup jugadores
            Object.values(datosDelJuego.players).forEach(datosJugador => {
                // añadimos un contador de "Energy" 
                this.getPlayerPanelElement(datosJugador.id).insertAdjacentHTML("beforeend", `\n <span id="energy-player-counter-${datosJugador.id}"></span> Energia\n `);

                // Creamos contador y añadimos valores
                (new ebg.counter).create(`energy-player-counter-${datosJugador.id}`, {
                    value: datosJugador.energy,
                    playerCounter: "energy",
                    playerId: datosJugador.id
                });

            });

            // Colocamos fichas iniciales
            this.addTokenOnBoard(datosDelJuego)

            // Prepara el sistema para recibir actualizaciones del servidor
            this.setupNotifications()
        },

        // Recibe los movimientos posibles del PHP, Llama a "updatePossibleMoves" para resaltarlos


        onEnteringState: function( stateName, args ){
            // console.log( 'Entering state: '+stateName );
            // console.log( 'State args:', args );
            
            switch( stateName ){
                case 'PlayerTurn':
                    // console.log('Possible moves:', args.args.possibleMoves);
                    this.updatePossibleMoves( args.args.possibleMoves );
                    // console.warn('Possible moves:', args.args.possibleMoves);
                    break;
            }
        },

        onLeavingState: function (e) { e }, 

        // actualiza botones de accion
        //  ejecuta cuando es el turno del jugador actual
        onUpdateActionButtons: function (e, t) {
            // console.error(e, t)

        },

        // al hacer click en una carta ...
        onCardClick: function (e) {
            // Envía una acción al servidor: "jugar carta con ID e"
            // bgaPerformAction es la forma de comunicarse con el backend
            this.bgaPerformAction("actPlayCard", { card_id: e }).then(() => { }) 
        },

        // Configurar notificaciones, Las notificaciones son mensajes del servidor ej: "jugador X jugó carta Y"
        setupNotifications: function () { 
            this.bgaSetupPromiseNotifications() 
        },

        //! Utility methods

        // funcion que coloca fichas concretas en el tablero usando la funcion "addDiscOnBoard"
        // legazy, quitamos este formato manual para poner el bueno
        /*
        addTokenOnBoard: function(localizacionFicha){
            // Si no se pasa parámetro, usar la variable de clase
            localizacionFicha = localizacionFicha || this.localizacionFicha

            // buscamos si en el HTML existe esa localizacion
            const cuadrado = document.getElementById(this.localizacionFicha);
            
            if (cuadrado) {
                this.addDiscOnBoard(2, 5, this.player_id, false);
            } else {
                console.error(`ERROR: No se encuentra ${localizacionFicha}`);
                console.log('Cuadrados disponibles:', Array.from(document.querySelectorAll('.square')).map(el => el.id));
            }
        }
        */

        // funcion usada para repartir peso de funcion del Setup
        addTokenOnBoard: function(datosDelJuego){
            for( var i in datosDelJuego.board ) {
                var square = datosDelJuego.board[i];
                
                if( square.player !== null )
                {
                    this.addDiscOnBoard( square.x, square.y, square.player, false );
                }
            }
        },

        // Funcion usada para poner un disco en tablero coin la animacion
        addDiscOnBoard: async function( x, y, playerId, animate = true ){

            const color = this.gamedatas.players[ playerId ].color;
            const discId = `disc_${x}_${y}`;

            document.getElementById(`square_${x}_${y}`).insertAdjacentHTML('beforeend', `
                <div class="disc" data-color="${color}" id="${discId}">
                    <div class="disc-faces">
                        <div class="disc-face" data-side="white"></div>
                        <div class="disc-face" data-side="black"></div>
                    </div>
                </div>
            `);

            if (animate) {
                const element = document.getElementById(discId);
                await this.animationManager.fadeIn(element, document.getElementById(`overall_player_board_${playerId}`));
            }
        },

        // funcion usada para mostrar los posibles movimientos
        updatePossibleMoves: function( possibleMoves ){
            // Quita todas las clases .possibleMove
            document.querySelectorAll('.possibleMove').forEach(div => div.classList.remove('possibleMove'));

            // Para cada movimiento válido, añade clase .possibleMove → se ve blanco semitransparente (CSS)
            for( var x in possibleMoves ) {
                for( var y in possibleMoves[ x ] ) {
                    // x,y is a possible move
                    document.getElementById(`square_${x}_${y}`).classList.add('possibleMove');
                }            
            }
                        
            this.addTooltipToClass( 'possibleMove', '', _('Place a disc here') );
        },
    })
});