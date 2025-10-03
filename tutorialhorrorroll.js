define([
    "dojo", "dojo/_base/declare", 
    "ebg/core/gamegui", 
    "ebg/counter",
    getLibUrl('bga-animations', '1.x')
],

function (e, declare, n, a, BgaAnimations) {
    return declare("bgagame.tutorialhorrorroll", ebg.core.gamegui, {

        // vairbale para probar que funcione bien la puesta de fichas
        //TODO una vez puesta a PROD borrar esta variable
        localizacionFicha : 'square_2_2',

        // constructor actualmente sin nada
        constructor: function() { 
            console.log('Bienvenido a reversi, mi tutorial'); 
        },

        // aqui construimos la interfaz inicial (solo se ejecuta 1 vez, al iniciar)
        setup: function(datosdelJuego) {
            console.warn('=== INICIO SETUP ===');

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
            Object.values(datosdelJuego.players).forEach(datosJugador => {
                // añadimos un contador de "Energy" 
                this.getPlayerPanelElement(datosJugador.id).insertAdjacentHTML("beforeend", `\n <span id="energy-player-counter-${datosJugador.id}"></span> Energia\n `);

                // Creamos contador y añadimos valores
                (new ebg.counter).create(`energy-player-counter-${datosJugador.id}`, {
                    value: datosJugador.energy,
                    playerCounter: "energy",
                    playerId: datosJugador.id
                });

            });

            // Prepara el sistema para recibir actualizaciones del servidor
            this.setupNotifications()

            // console.log('=== FIN SETUP, LLAMANDO addTokenOnBoard ===');
            this.addTokenOnBoard()

        },

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

        // tanto al salir como al entrar del estado actualmente esta vacio
        onEnteringState: function (e, t) { e },
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

        

        // para probar que funcione bien
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
    })
});