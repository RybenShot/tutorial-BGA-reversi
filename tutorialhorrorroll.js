define([
    "dojo", "dojo/_base/declare", 
    "ebg/core/gamegui", 
    "ebg/counter",
    getLibUrl('bga-animations', '1.x')
],

function (dojo, declare, gamegui, counter, BgaAnimations) {
    return declare("bgagame.tutorialhorrorroll", ebg.core.gamegui, {

        // variable para probar que funcione bien la puesta de fichas
        //TODO una vez puesta a PROD borrar esta variable
        localizacionFicha : 'square_2_2',

        // constructor actualmente sin nada
        constructor: function(){
            console.log('reversi constructor');
            this.preference_coordinates = 100;
            this.preference_confirm = 101;
            this.preference_sound = 102;
        },

        // aqui construimos la interfaz inicial (solo se ejecuta 1 vez, al iniciar), Constructor de la interfaz
        setup: function(datosDelJuego) {
            console.warn('=== INICIO SETUP!! ===');
            
            // Inicializar el gestor de animaciones
            this.animationManager = new BgaAnimations.Manager({
                animationsActive: () => this.bgaAnimationsActive(),
            });

            // Creamos el board en el DOM
            this.getGameAreaElement().insertAdjacentHTML("beforeend", '\n <div id="board"></div>\n ');

            const board = document.getElementById('board');
            // console.log('Board encontrado:', board);

            const board_size = datosDelJuego.board_size;
            const cell_size = 60;
            this.interface_min_width = Math.min(760, cell_size * board_size + 40);
            board.style.width = (cell_size * board_size) + 'px';
            board.style.height = (cell_size * board_size) + 'px';

            // Generar dinámicamente las casillas y coordenadas del tablero
            for (let x = 1; x <= board_size; x++) {
                // Add coordinates (A-H, 1-8)
                const coord = Math.round((x - 1) * cell_size) + 1 + (cell_size - 20) / 2;
                board.insertAdjacentHTML(`afterbegin`, `<div id="coordinate_row_${x}" class="coordinate_cell" style="left: ${coord}px; top: -20px;">${String.fromCharCode(64 + x)}</div>`);
                board.insertAdjacentHTML(`afterbegin`, `<div id="coordinate_column_${x}" class="coordinate_cell" style="left: -20px; top: ${coord}px;">${x}</div>`);

                // Añadir casillas del tablero
                for (let y = 1; y <= board_size; y++) {
                    const left = Math.round((x - 1) * cell_size) + 1;
                    const top = Math.round((y - 1) * cell_size) + 1;
                    board.insertAdjacentHTML(`afterbegin`, `<div id="square_${x}_${y}" class="square board" style="left: ${left}px; top: ${top}px;"></div>`);
                }        
            }


            // Colocamos fichas iniciales
            this.addTokenOnBoard(datosDelJuego)

            // Preparamos la accion de "click" para cada casilla
            document.querySelectorAll('.square').forEach(square => square.addEventListener('click', event => this.onPlayDisc(event)));

            // Prepara el sistema para recibir actualizaciones del servidor
            this.setupNotifications()

        },

        // funcion usada para repartir peso de funcion del Setup
        //Coloca las fichas iniciales en el tablero según los datos del juego
        addTokenOnBoard: function(datosDelJuego){
            console.log("pasando por addTokenOnBoard")

            for( var i in datosDelJuego.board ) {
                var square = datosDelJuego.board[i];
                
                if( square.player !== null )
                {
                    this.addDiscOnBoard( square.x, square.y, square.player, false );
                }
            }
        },

        /**
         * Añade un disco en el tablero en la posición especificada.
         * @param {number} x - Coordenada X
         * @param {number} y - Coordenada Y
         * @param {number} playerId - ID del jugador propietario del disco
         * @param {boolean} animate - Si debe mostrar animación de entrada
         */
        addDiscOnBoard: async function( x, y, playerId, animate = true ){
            console.log("pasando por addDiscOnBoard")

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

        // Configurar notificaciones, Las notificaciones son mensajes del servidor ej: "jugador X jugó carta Y"
        setupNotifications: function () { 
            console.log("pasando por setupNotifications")
            this.bgaSetupPromiseNotifications() 
        },

        // actualiza botones de accion
        //  ejecuta cuando es el turno del jugador actual
        onUpdateActionButtons: function (stateName, args) {
            console.log("pasando por onUpdateActionButtons")
        },

        /**
         * Se ejecuta al entrar en un nuevo estado del juego.
         * Muestra los movimientos posibles cuando es el turno del jugador activo.
         */
        onEnteringState: function( stateName, args ){
            console.log("pasando por onEnteringState")
            
            switch (stateName) {
                case 'PlayDisc':
                    if (this.isCurrentPlayerActive()) {
                        console.error(this.isCurrentPlayerActive())
                        const possibleMoves = args.args.possibleMoves;
                        // Marcamos visualmente los movimientos posibles
                        for( var x in possibleMoves ) {
                            for( var y in possibleMoves[ x ] ) {
                                document.getElementById(`square_${x}_${y}`).insertAdjacentHTML('beforeend', `<div class="possibleMove" id="square_${x}_${y}_possibleMove"></div>`);
                                document.getElementById(`square_${x}_${y}`).style.cursor = 'pointer';
                            }            
                        }
                    }
                    console.warn(this.isCurrentPlayerActive())
                    break;
            }
        },

        // Metodo que capta el click en una casilla
        onPlayDisc: function( event ){
            console.log("pasando por onPlayDisc")

            // Evitar propagación del evento
            event.preventDefault();
            event.stopPropagation();

            // El click no hace nada si no es tu turno
            if (!this.isCurrentPlayerActive()) {
                return;
            }

            // Obtener coordenadas X e Y desde el ID de la casilla RECUERDA: Formato del ID: "square_X_Y"
            var coordenadas = event.currentTarget.id.split('_');
            var x = coordenadas[1];
            var y = coordenadas[2];

            // Si el div NO tiene la clase "possibleMove" ...
            if(!document.getElementById(`square_${x}_${y}_possibleMove`)) { //!
                // no hacemos nada ...
                return;
            }

            console.warn("coodernadas:", x, y )
            console.log(this.bgaPerformAction("actPlayDisc", {x:x, y:y}))
            // Enviar acción al servidor
            this.bgaPerformAction("actPlayDisc", {x:x, y:y})
            
        },

        // Metodo para animar la ficha en movimiento
        animateTurnOverDisc: async function(disc, targetColor) {
            const squareDiv = document.getElementById(`square_${disc.x}_${disc.y}`);
            const discDiv = document.getElementById(`disc_${disc.x}_${disc.y}`);
            
            squareDiv.classList.add('flip-animation');
            await this.wait(500);


            discDiv.dataset.color = targetColor;

            const parallelAnimations = [{
                keyframes: [
                    { transform: `rotateY(180deg)` },
                    { transform: `rotateY(0deg)` },
                ]
            }, {
                keyframes: [
                    { transform: `translate(0, -12px) scale(1.2)`, offset: 0.5 },
                ]
            }];

            await this.animationManager.slideAndAttach(discDiv, squareDiv, { duration: 1000, parallelAnimations });
            
            squareDiv.classList.remove('flip-animation');
            await this.wait(500);
        },

        // Notificación: Voltear discos capturados 
        notif_turnOverDiscs: async function( args ) {

            const targetColor = this.gamedatas.players[ args.player_id ].color;

            // Animar todos los discos volteados en paralelo
            await Promise.all(
                args.turnedOver.map(disc => 
                    this.animateTurnOverDisc(disc, targetColor)
                )
            );

        },

        // Notificación: Colocar un disco en el tablero Se ejecuta cuando un jugador realiza un movimiento válido
        notif_playDisc: async function( args )
        {
            console.log("Notificación playDisc recibida:", args);
            
            // Limpiar los marcadores de movimientos posibles
            document.querySelectorAll('.possibleMove').forEach(div => div.remove());
            document.querySelectorAll('.square').forEach(square => {
                square.style.cursor = '';
            });

            // Añadimos el disco en el tablero con animación
            await this.addDiscOnBoard( args.x, args.y, args.player_id, true );
        },

        //!

        onLeavingState: function (e) { 
            console.log("pasando por onLeavingState")
        }, 

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

    })
});