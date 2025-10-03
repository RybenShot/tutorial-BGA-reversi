define(
    // importamos las cosas que nos van a hacer falta
    ["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"],

    function (e, t, n, a) {
        return t("bgagame.tutorialhorrorroll", ebg.core.gamegui,
            {
                // constructor actualmente sin nada
                constructor: function () { console.log('Bienvenido a reversi, mi tutorial'); },

                // aqui construimos la interfaz inicial (solo se ejecuta 1 vez, al iniciar)
                setup: function (datosdelJuego) {
                    this.getGameAreaElement().insertAdjacentHTML("beforeend", '\n <div id="board"></div>\n '); // Creamos el area principal del juego

                    const board = document.getElementById('board');
                    const hor_scale = 64.8;
                    const ver_scale = 64.4;
                    for (let x=1; x<=8; x++) {
                        for (let y=1; y<=8; y++) {
                            const left = Math.round((x - 1) * hor_scale + 10);
                            const top = Math.round((y - 1) * ver_scale + 7);
                            // we use afterbegin to make sure squares are placed before discs
                            board.insertAdjacentHTML(`afterbegin`, `<div id="square${x}${y}" class="square" style="left: ${left}px; top: ${top}px;"></div>`);
                        }
                    }

                    // recorremos para cada jugador ...
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

                },

                // tanto al salir como al entrar del estado actualmente esta vacio
                onEnteringState: function (e, t) { e },
                onLeavingState: function (e) { e }, 

                // actualiza botones de accion
                //  ejecuta cuando es el turno del jugador actual
                onUpdateActionButtons: function (e, t) {
                    console.error(e, t)
                    if (this.isCurrentPlayerActive() && "PlayerTurn" === e) {

                        //TODO en que momento se crea las cartas que estan guardadads en la variable "t" ????

                        // creamos 2 botones simples para poder jugar
                        t.playableCardsIds.forEach(e => 
                            // al diferencia del siguiente boton, como aqui tenemos 2 botones hacemos un bucle para manejar los 2 de un tiron
                            this.statusBar.addActionButton(
                                ("Play card with id ${card_id}").replace("${card_id}", e),
                                () => this.onCardClick(e),
                                console.log(`se va a jugar la carta: ${e}`)
                            )
                        );

                        // creamos 1 boton mas para ejecutar la funcion de pasar
                        this.statusBar.addActionButton(("Pass"),
                            () => this.bgaPerformAction("actPass"), 
                            { color: "secondary" },
                            console.log(`El jugador ha preferido pasar`)
                        )
                    }
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
                }
            }
        )
    }
);