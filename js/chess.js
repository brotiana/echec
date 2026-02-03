 

class ChessGame {
    constructor() {
        this.board = {};
        this.currentTurn = 'white';
        this.selectedPiece = null;
        this.validMoves = [];
        this.lastMove = null;
        this.moveHistory = [];
        this.isCheck = false;
        this.isCheckmate = false;
        this.isDraw = false;
        this.castlingRights = {
            white: { kingSide: true, queenSide: true },
            black: { kingSide: true, queenSide: true }
        };
        this.enPassantTarget = null;
        this.halfMoveClock = 0;
        this.moveCallback = null;
        this.isAIMoving = false;  
        this.playerColor = 'white';  
    }

     
    static INITIAL_POSITION = {
        'a8': 'black_rook', 'b8': 'black_knight', 'c8': 'black_bishop', 'd8': 'black_queen',
        'e8': 'black_king', 'f8': 'black_bishop', 'g8': 'black_knight', 'h8': 'black_rook',
        'a7': 'black_pawn', 'b7': 'black_pawn', 'c7': 'black_pawn', 'd7': 'black_pawn',
        'e7': 'black_pawn', 'f7': 'black_pawn', 'g7': 'black_pawn', 'h7': 'black_pawn',
        'a2': 'white_pawn', 'b2': 'white_pawn', 'c2': 'white_pawn', 'd2': 'white_pawn',
        'e2': 'white_pawn', 'f2': 'white_pawn', 'g2': 'white_pawn', 'h2': 'white_pawn',
        'a1': 'white_rook', 'b1': 'white_knight', 'c1': 'white_bishop', 'd1': 'white_queen',
        'e1': 'white_king', 'f1': 'white_bishop', 'g1': 'white_knight', 'h1': 'white_rook'
    };

     
    init(boardState = null, currentTurn = 'white') {
        this.board = boardState ? { ...boardState } : { ...ChessGame.INITIAL_POSITION };
        this.currentTurn = currentTurn;
        this.selectedPiece = null;
        this.validMoves = [];
        this.lastMove = null;
        this.moveHistory = [];
        this.isCheck = false;
        this.isCheckmate = false;
        this.isDraw = false;
        this.isAIMoving = false;
        this.castlingRights = {
            white: { kingSide: true, queenSide: true },
            black: { kingSide: true, queenSide: true }
        };
        this.renderBoard();
    }

     
    setPlayerColor(color) {
        this.playerColor = color;
    }

     
    getPieceImage(piece) {
        const color = this.getPieceColor(piece);
        const type = this.getPieceType(piece);
        const fillColor = color === 'white' ? '#fff' : '#000';
        const strokeColor = color === 'white' ? '#000' : '#fff';

        const pieces = {
            'king': `<svg viewBox="0 0 45 45"><g fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22.5 11.63V6M20 8h5"/><path d="M22.5 25s4.5-7.5 3-10.5c0 0-1-2.5-3-2.5s-3 2.5-3 2.5c-1.5 3 3 10.5 3 10.5" fill="${fillColor}" stroke-linecap="butt"/><path d="M11.5 37c5.5 3.5 15.5 3.5 21 0v-7s9-4.5 6-10.5c-4-6.5-13.5-3.5-16 4V27v-3.5c-3.5-7.5-13-10.5-16-4-3 6 5 10 5 10V37z" fill="${fillColor}"/><path d="M11.5 30c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0"/></g></svg>`,
            'queen': `<svg viewBox="0 0 45 45"><g fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="12" r="2.75"/><circle cx="14" cy="9" r="2.75"/><circle cx="22.5" cy="8" r="2.75"/><circle cx="31" cy="9" r="2.75"/><circle cx="39" cy="12" r="2.75"/><path d="M9 26c8.5-1.5 21-1.5 27 0l2.5-12.5L31 25l-.3-14.1-5.2 13.6-3-14.5-3 14.5-5.2-13.6L14 25 6.5 13.5 9 26z" stroke-linecap="butt"/><path d="M9 26c0 2 1.5 2 2.5 4 1 1.5 1 1 .5 3.5-1.5 1-1.5 2.5-1.5 2.5-1.5 1.5.5 2.5.5 2.5 6.5 1 16.5 1 23 0 0 0 1.5-1 0-2.5 0 0 .5-1.5-1-2.5-.5-2.5-.5-2 .5-3.5 1-2 2.5-2 2.5-4-8.5-1.5-18.5-1.5-27 0z" stroke-linecap="butt"/><path d="M11 38.5a35 35 1 0 0 23 0" fill="none" stroke-linecap="butt"/><path d="M11 29a35 35 1 0 1 23 0m-21.5 2.5h20m-21 3a35 35 1 0 0 22 0" fill="none"/></g></svg>`,
            'rook': `<svg viewBox="0 0 45 45"><g fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 39h27v-3H9v3zm3-3v-4h21v4H12zm-1-22V9h4v2h5V9h5v2h5V9h4v5" stroke-linecap="butt"/><path d="M34 14l-3 3H14l-3-3"/><path d="M31 17v12.5H14V17" stroke-linecap="butt" stroke-linejoin="miter"/><path d="M31 29.5l1.5 2.5h-20l1.5-2.5"/><path d="M11 14h23" fill="none" stroke-linejoin="miter"/></g></svg>`,
            'bishop': `<svg viewBox="0 0 45 45"><g fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g fill="${fillColor}" stroke-linecap="butt"><path d="M9 36c3.39-.97 10.11.43 13.5-2 3.39 2.43 10.11 1.03 13.5 2 0 0 1.65.54 3 2-.68.97-1.65.99-3 .5-3.39-.97-10.11.46-13.5-1-3.39 1.46-10.11.03-13.5 1-1.354.49-2.323.47-3-.5 1.354-1.94 3-2 3-2z"/><path d="M15 32c2.5 2.5 12.5 2.5 15 0 .5-1.5 0-2 0-2 0-2.5-2.5-4-2.5-4 5.5-1.5 6-11.5-5-15.5-11 4-10.5 14-5 15.5 0 0-2.5 1.5-2.5 4 0 0-.5.5 0 2z"/><path d="M25 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z"/></g><path d="M17.5 26h10M15 30h15m-7.5-14.5v5M20 18h5" fill="none" stroke="${strokeColor}" stroke-linejoin="miter"/></g></svg>`,
            'knight': `<svg viewBox="0 0 45 45"><g fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10c10.5 1 16.5 8 16 29H15c0-9 10-6.5 8-21" fill="${fillColor}"/><path d="M24 18c.38 2.91-5.55 7.37-8 9-3 2-2.82 4.34-5 4-1.042-.94 1.41-3.04 0-3-1 0 .19 1.23-1 2-1 0-4.003 1-4-4 0-2 6-12 6-12s1.89-1.9 2-3.5c-.73-.994-.5-2-.5-3 1-1 3 2.5 3 2.5h2s.78-1.992 2.5-3c1 0 1 3 1 3" fill="${fillColor}"/><path d="M9.5 25.5a.5.5 0 1 1-1 0 .5.5 0 1 1 1 0z" fill="${strokeColor}" stroke="${strokeColor}"/><path d="M14.933 15.75a.5 1.5 30 1 1-.866-.5.5 1.5 30 1 1 .866.5z" fill="${strokeColor}" stroke="${strokeColor}" stroke-width="1.49997"/></g></svg>`,
            'pawn': `<svg viewBox="0 0 45 45"><path d="M22.5 9c-2.21 0-4 1.79-4 4 0 .89.29 1.71.78 2.38C17.33 16.5 16 18.59 16 21c0 2.03.94 3.84 2.41 5.03-3 1.06-7.41 5.55-7.41 13.47h23c0-7.92-4.41-12.41-7.41-13.47 1.47-1.19 2.41-3 2.41-5.03 0-2.41-1.33-4.5-3.28-5.62.49-.67.78-1.49.78-2.38 0-2.21-1.79-4-4-4z" fill="${fillColor}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round"/></svg>`
        };

        return pieces[type] || '';
    }

     
    renderBoard() {
        const boardElement = document.getElementById('chess-board');
        if (!boardElement) return;

        boardElement.innerHTML = '';

         
        let files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        let ranks = ['8', '7', '6', '5', '4', '3', '2', '1'];

        if (this.playerColor === 'black') {
            files = files.slice().reverse();   
            ranks = ranks.slice().reverse();   
        }

        for (let rankIndex = 0; rankIndex < 8; rankIndex++) {
            for (let fileIndex = 0; fileIndex < 8; fileIndex++) {
                const file = files[fileIndex];
                const rank = ranks[rankIndex];
                const position = file + rank;

                 
                const originalFileIndex = file.charCodeAt(0) - 97;  
                const originalRankIndex = parseInt(rank) - 1;  
                const isLight = (originalFileIndex + originalRankIndex) % 2 === 1;

                const square = document.createElement('div');
                square.className = `chess-square ${isLight ? 'light' : 'dark'}`;
                square.dataset.position = position;
                square.onclick = () => this.handleSquareClick(position);

                 
                if (fileIndex === 0) {
                    const coord = document.createElement('span');
                    coord.className = 'board-coords coord-number';
                    coord.textContent = rank;
                    square.appendChild(coord);
                }
                if (rankIndex === 7) {
                    const coord = document.createElement('span');
                    coord.className = 'board-coords coord-letter';
                    coord.textContent = file;
                    square.appendChild(coord);
                }

                 
                const piece = this.board[position];
                if (piece) {
                    const pieceDiv = document.createElement('div');
                    pieceDiv.className = 'chess-piece';
                    pieceDiv.innerHTML = this.getPieceImage(piece);
                    square.appendChild(pieceDiv);
                }

                 
                if (this.lastMove && (position === this.lastMove.from || position === this.lastMove.to)) {
                    square.classList.add('last-move');
                }

                 
                if (this.isCheck && piece && piece.includes('king') &&
                    piece.startsWith(this.currentTurn)) {
                    square.classList.add('check');
                }

                boardElement.appendChild(square);
            }
        }
    }

     
    handleSquareClick(position) {
         
        if (this.isAIMoving) return;

         
        if (this.currentTurn !== this.playerColor) return;

        const piece = this.board[position];

         
        if (this.selectedPiece) {
             
            if (this.validMoves.includes(position)) {
                this.makeMove(this.selectedPiece, position);
            } else if (piece && this.getPieceColor(piece) === this.currentTurn) {
                 
                this.selectPiece(position);
            } else {
                this.deselectPiece();
            }
        } else if (piece && this.getPieceColor(piece) === this.currentTurn) {
            this.selectPiece(position);
        }
    }

     
    selectPiece(position) {
        this.deselectPiece();
        this.selectedPiece = position;
        this.validMoves = this.getValidMoves(position);

         
        const square = document.querySelector(`[data-position="${position}"]`);
        if (square) square.classList.add('selected');

         
        this.validMoves.forEach(move => {
            const moveSquare = document.querySelector(`[data-position="${move}"]`);
            if (moveSquare) {
                if (this.board[move]) {
                    moveSquare.classList.add('valid-capture');
                } else {
                    moveSquare.classList.add('valid-move');
                }
            }
        });
    }

     
    deselectPiece() {
        if (this.selectedPiece) {
            const square = document.querySelector(`[data-position="${this.selectedPiece}"]`);
            if (square) square.classList.remove('selected');
        }

        document.querySelectorAll('.valid-move, .valid-capture').forEach(el => {
            el.classList.remove('valid-move', 'valid-capture');
        });

        this.selectedPiece = null;
        this.validMoves = [];
    }

     
    getPieceColor(piece) {
        return piece.startsWith('white') ? 'white' : 'black';
    }

     
    getPieceType(piece) {
        return piece.split('_')[1];
    }

     
    positionToCoords(pos) {
        const file = pos.charCodeAt(0) - 97;  
        const rank = parseInt(pos[1]) - 1;    
        return { file, rank };
    }

     
    coordsToPosition(file, rank) {
        if (file < 0 || file > 7 || rank < 0 || rank > 7) return null;
        return String.fromCharCode(97 + file) + (rank + 1);
    }

     
    getValidMoves(position) {
        const piece = this.board[position];
        if (!piece) return [];

        const color = this.getPieceColor(piece);
        const type = this.getPieceType(piece);
        let moves = [];

        switch (type) {
            case 'pawn':
                moves = this.getPawnMoves(position, color);
                break;
            case 'rook':
                moves = this.getRookMoves(position, color);
                break;
            case 'knight':
                moves = this.getKnightMoves(position, color);
                break;
            case 'bishop':
                moves = this.getBishopMoves(position, color);
                break;
            case 'queen':
                moves = this.getQueenMoves(position, color);
                break;
            case 'king':
                moves = this.getKingMoves(position, color);
                break;
        }

         
        return moves.filter(move => !this.wouldBeInCheck(position, move, color));
    }

     
    getPawnMoves(position, color) {
        const moves = [];
        const { file, rank } = this.positionToCoords(position);
        const direction = color === 'white' ? 1 : -1;
        const startRank = color === 'white' ? 1 : 6;

         
        let newPos = this.coordsToPosition(file, rank + direction);
        if (newPos && !this.board[newPos]) {
            moves.push(newPos);

             
            if (rank === startRank) {
                newPos = this.coordsToPosition(file, rank + 2 * direction);
                if (newPos && !this.board[newPos]) {
                    moves.push(newPos);
                }
            }
        }

         
        for (const df of [-1, 1]) {
            newPos = this.coordsToPosition(file + df, rank + direction);
            if (newPos) {
                if (this.board[newPos] && this.getPieceColor(this.board[newPos]) !== color) {
                    moves.push(newPos);
                }
                 
                if (newPos === this.enPassantTarget) {
                    moves.push(newPos);
                }
            }
        }

        return moves;
    }

     
    getRookMoves(position, color) {
        return this.getSlidingMoves(position, color, [[0, 1], [0, -1], [1, 0], [-1, 0]]);
    }

     
    getBishopMoves(position, color) {
        return this.getSlidingMoves(position, color, [[1, 1], [1, -1], [-1, 1], [-1, -1]]);
    }

     
    getQueenMoves(position, color) {
        return [
            ...this.getRookMoves(position, color),
            ...this.getBishopMoves(position, color)
        ];
    }

     
    getSlidingMoves(position, color, directions) {
        const moves = [];
        const { file, rank } = this.positionToCoords(position);

        for (const [df, dr] of directions) {
            let f = file + df;
            let r = rank + dr;

            while (f >= 0 && f <= 7 && r >= 0 && r <= 7) {
                const newPos = this.coordsToPosition(f, r);
                const targetPiece = this.board[newPos];

                if (!targetPiece) {
                    moves.push(newPos);
                } else {
                    if (this.getPieceColor(targetPiece) !== color) {
                        moves.push(newPos);
                    }
                    break;
                }

                f += df;
                r += dr;
            }
        }

        return moves;
    }

     
    getKnightMoves(position, color) {
        const moves = [];
        const { file, rank } = this.positionToCoords(position);
        const jumps = [
            [-2, -1], [-2, 1], [-1, -2], [-1, 2],
            [1, -2], [1, 2], [2, -1], [2, 1]
        ];

        for (const [df, dr] of jumps) {
            const newPos = this.coordsToPosition(file + df, rank + dr);
            if (newPos) {
                const targetPiece = this.board[newPos];
                if (!targetPiece || this.getPieceColor(targetPiece) !== color) {
                    moves.push(newPos);
                }
            }
        }

        return moves;
    }

     
    getKingMoves(position, color) {
        const moves = [];
        const { file, rank } = this.positionToCoords(position);

        for (let df = -1; df <= 1; df++) {
            for (let dr = -1; dr <= 1; dr++) {
                if (df === 0 && dr === 0) continue;
                const newPos = this.coordsToPosition(file + df, rank + dr);
                if (newPos) {
                    const targetPiece = this.board[newPos];
                    if (!targetPiece || this.getPieceColor(targetPiece) !== color) {
                        moves.push(newPos);
                    }
                }
            }
        }

         
        if (!this.isSquareAttacked(position, color)) {
            const baseRank = color === 'white' ? '1' : '8';

             
            if (this.castlingRights[color].kingSide) {
                const f = this.coordsToPosition(5, parseInt(baseRank) - 1);
                const g = this.coordsToPosition(6, parseInt(baseRank) - 1);
                if (!this.board[f] && !this.board[g] &&
                    !this.isSquareAttacked(f, color) &&
                    !this.isSquareAttacked(g, color)) {
                    moves.push(g);
                }
            }

             
            if (this.castlingRights[color].queenSide) {
                const b = this.coordsToPosition(1, parseInt(baseRank) - 1);
                const c = this.coordsToPosition(2, parseInt(baseRank) - 1);
                const d = this.coordsToPosition(3, parseInt(baseRank) - 1);
                if (!this.board[b] && !this.board[c] && !this.board[d] &&
                    !this.isSquareAttacked(c, color) &&
                    !this.isSquareAttacked(d, color)) {
                    moves.push(c);
                }
            }
        }

        return moves;
    }

     
    isSquareAttacked(position, byColor) {
        const oppositeColor = byColor === 'white' ? 'black' : 'white';

        for (const [pos, piece] of Object.entries(this.board)) {
            if (piece && this.getPieceColor(piece) === oppositeColor) {
                const type = this.getPieceType(piece);
                let attackMoves = [];

                switch (type) {
                    case 'pawn':
                         
                        const { file, rank } = this.positionToCoords(pos);
                        const dir = oppositeColor === 'white' ? 1 : -1;
                        attackMoves = [
                            this.coordsToPosition(file - 1, rank + dir),
                            this.coordsToPosition(file + 1, rank + dir)
                        ].filter(Boolean);
                        break;
                    case 'knight':
                        attackMoves = this.getKnightMoves(pos, oppositeColor);
                        break;
                    case 'bishop':
                        attackMoves = this.getBishopMoves(pos, oppositeColor);
                        break;
                    case 'rook':
                        attackMoves = this.getRookMoves(pos, oppositeColor);
                        break;
                    case 'queen':
                        attackMoves = this.getQueenMoves(pos, oppositeColor);
                        break;
                    case 'king':
                         
                        const kingCoords = this.positionToCoords(pos);
                        for (let df = -1; df <= 1; df++) {
                            for (let dr = -1; dr <= 1; dr++) {
                                if (df !== 0 || dr !== 0) {
                                    const p = this.coordsToPosition(
                                        kingCoords.file + df,
                                        kingCoords.rank + dr
                                    );
                                    if (p) attackMoves.push(p);
                                }
                            }
                        }
                        break;
                }

                if (attackMoves.includes(position)) {
                    return true;
                }
            }
        }

        return false;
    }

     
    wouldBeInCheck(from, to, color) {
         
        const originalBoard = { ...this.board };
        const piece = this.board[from];

        this.board[to] = piece;
        delete this.board[from];

         
        let kingPos = null;
        for (const [pos, p] of Object.entries(this.board)) {
            if (p === `${color}_king`) {
                kingPos = pos;
                break;
            }
        }

        const inCheck = this.isSquareAttacked(kingPos, color);

         
        this.board = originalBoard;

        return inCheck;
    }

     
    makeMove(from, to, isAIMove = false) {
        const piece = this.board[from];
        if (!piece) return false;

        const color = this.getPieceColor(piece);
        const type = this.getPieceType(piece);
        let capturedPiece = this.board[to] || null;

         
        if (type === 'king') {
            const fromCoords = this.positionToCoords(from);
            const toCoords = this.positionToCoords(to);

             
            if (toCoords.file - fromCoords.file === 2) {
                const rookFrom = this.coordsToPosition(7, fromCoords.rank);
                const rookTo = this.coordsToPosition(5, fromCoords.rank);
                this.board[rookTo] = this.board[rookFrom];
                delete this.board[rookFrom];
            }
             
            else if (fromCoords.file - toCoords.file === 2) {
                const rookFrom = this.coordsToPosition(0, fromCoords.rank);
                const rookTo = this.coordsToPosition(3, fromCoords.rank);
                this.board[rookTo] = this.board[rookFrom];
                delete this.board[rookFrom];
            }

             
            this.castlingRights[color].kingSide = false;
            this.castlingRights[color].queenSide = false;
        }

         
        if (type === 'rook') {
            const fromCoords = this.positionToCoords(from);
            if (fromCoords.file === 0) {
                this.castlingRights[color].queenSide = false;
            } else if (fromCoords.file === 7) {
                this.castlingRights[color].kingSide = false;
            }
        }

         
        if (type === 'pawn' && to === this.enPassantTarget) {
            const direction = color === 'white' ? -1 : 1;
            const capturedPos = this.coordsToPosition(
                this.positionToCoords(to).file,
                this.positionToCoords(to).rank + direction
            );
            capturedPiece = this.board[capturedPos];
            delete this.board[capturedPos];
        }

         
        this.enPassantTarget = null;
        if (type === 'pawn') {
            const fromCoords = this.positionToCoords(from);
            const toCoords = this.positionToCoords(to);
            if (Math.abs(toCoords.rank - fromCoords.rank) === 2) {
                this.enPassantTarget = this.coordsToPosition(
                    fromCoords.file,
                    (fromCoords.rank + toCoords.rank) / 2
                );
            }
        }

         
        const fromSquare = document.querySelector(`[data-position="${from}"]`);
        const toSquare = document.querySelector(`[data-position="${to}"]`);
        let fromRect, toRect;

        if (fromSquare && toSquare) {
            fromRect = fromSquare.getBoundingClientRect();
            toRect = toSquare.getBoundingClientRect();
        }

         
        this.board[to] = piece;
        delete this.board[from];

         
        if (type === 'pawn') {
            const toRank = this.positionToCoords(to).rank;
            if ((color === 'white' && toRank === 7) || (color === 'black' && toRank === 0)) {
                 
                this.board[to] = `${color}_queen`;
            }
        }

         
        this.lastMove = { from, to, piece, captured: capturedPiece };
        this.moveHistory.push(this.lastMove);

         
        this.currentTurn = color === 'white' ? 'black' : 'white';

         
        this.checkGameState();

         
        this.deselectPiece();
        this.renderBoard();

         
        if (fromRect && toRect) {
            const newPiece = document.querySelector(`[data-position="${to}"] .chess-piece`);
            if (newPiece) {
                const deltaX = fromRect.left - toRect.left;
                const deltaY = fromRect.top - toRect.top;

                 
                newPiece.style.transition = 'none';
                newPiece.style.transform = `translate(${deltaX}px, ${deltaY}px)`;
                newPiece.style.zIndex = '100';

                 
                newPiece.getBoundingClientRect();

                 
                newPiece.style.transition = 'transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1)';
                newPiece.style.transform = '';

                 
                setTimeout(() => {
                    newPiece.style.zIndex = '';
                }, 300);
            }
        }

         
        if (!isAIMove && this.moveCallback) {
            this.moveCallback(
                from, to, piece,
                { ...this.board },
                capturedPiece,
                this.isCheck,
                this.isCheckmate
            );
        }

        return true;
    }

     
    checkGameState() {
         
        let kingPos = null;
        for (const [pos, piece] of Object.entries(this.board)) {
            if (piece === `${this.currentTurn}_king`) {
                kingPos = pos;
                break;
            }
        }

         
        this.isCheck = this.isSquareAttacked(kingPos, this.currentTurn);

         
        let hasLegalMoves = false;
        for (const [pos, piece] of Object.entries(this.board)) {
            if (piece && this.getPieceColor(piece) === this.currentTurn) {
                if (this.getValidMoves(pos).length > 0) {
                    hasLegalMoves = true;
                    break;
                }
            }
        }

        if (!hasLegalMoves) {
            if (this.isCheck) {
                this.isCheckmate = true;
            } else {
                this.isDraw = true;  
            }
        }
    }

     
    updateBoardState(newState, newTurn = null, lastMove = null) {
         
        let fromRect, toRect;
        let fromSquare, toSquare;

        if (lastMove && lastMove.from_position && lastMove.to_position) {
             
            this.lastMove = {
                from: lastMove.from_position,
                to: lastMove.to_position,
                piece: lastMove.piece_type  
            };

             
            fromSquare = document.querySelector(`[data-position="${lastMove.from_position}"]`);
            toSquare = document.querySelector(`[data-position="${lastMove.to_position}"]`);

            if (fromSquare && toSquare) {
                 
                 
                 
                const pieceElement = fromSquare.querySelector('.chess-piece');
                if (pieceElement) {
                    fromRect = pieceElement.getBoundingClientRect();
                     
                    toRect = toSquare.getBoundingClientRect();
                }
            }
        }

        this.board = { ...newState };
        if (newTurn) {
            this.currentTurn = newTurn;
        }
        this.renderBoard();

         
        if (fromRect && toRect && lastMove) {
            const newPiece = document.querySelector(`[data-position="${lastMove.to_position}"] .chess-piece`);
            if (newPiece) {
                 
                const newToRect = newPiece.parentElement.getBoundingClientRect();  

                const deltaX = fromRect.left - newToRect.left;  
                 
                 
                const currentPieceRect = newPiece.getBoundingClientRect();

                const dX = fromRect.left - currentPieceRect.left;
                const dY = fromRect.top - currentPieceRect.top;

                newPiece.style.transition = 'none';
                newPiece.style.transform = `translate(${dX}px, ${dY}px)`;
                newPiece.style.zIndex = '100';

                 
                newPiece.getBoundingClientRect();

                newPiece.style.transition = 'transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1)';
                newPiece.style.transform = '';

                setTimeout(() => {
                    newPiece.style.zIndex = '';
                }, 300);
            }
        }

         
        this.enPassantTarget = null;
        if (lastMove && lastMove.from_position && lastMove.to_position) {
             
             
            const isPawn = lastMove.piece_type && lastMove.piece_type.includes('pawn');
            if (isPawn) {
                const fromC = this.positionToCoords(lastMove.from_position);
                const toC = this.positionToCoords(lastMove.to_position);
                if (Math.abs(toC.rank - fromC.rank) === 2) {
                    this.enPassantTarget = this.coordsToPosition(
                        fromC.file,
                        (fromC.rank + toC.rank) / 2
                    );
                }
            }
        }
    }

     
    makeAIMove() {
        if (this.isAIMoving) return null;

        this.isAIMoving = true;

        const allMoves = [];

        for (const [pos, piece] of Object.entries(this.board)) {
            if (piece && this.getPieceColor(piece) === this.currentTurn) {
                const moves = this.getValidMoves(pos);
                moves.forEach(move => {
                    allMoves.push({ from: pos, to: move });
                });
            }
        }

        if (allMoves.length === 0) {
            this.isAIMoving = false;
            return null;
        }

         
        const scoredMoves = allMoves.map(move => {
            let score = Math.random() * 10;  

             
            const targetPiece = this.board[move.to];
            if (targetPiece) {
                const values = { 'pawn': 1, 'knight': 3, 'bishop': 3, 'rook': 5, 'queen': 9, 'king': 100 };
                score += values[this.getPieceType(targetPiece)] * 10;
            }

             
            const centerSquares = ['d4', 'd5', 'e4', 'e5'];
            if (centerSquares.includes(move.to)) {
                score += 2;
            }

             
            const piece = this.board[move.from];
            if (this.getPieceType(piece) === 'knight' || this.getPieceType(piece) === 'bishop') {
                if (this.moveHistory.length < 10) {
                    score += 3;
                }
            }

            return { ...move, score };
        });

         
        scoredMoves.sort((a, b) => b.score - a.score);
        const topMoves = scoredMoves.slice(0, Math.min(3, scoredMoves.length));
        const selectedMove = topMoves[Math.floor(Math.random() * topMoves.length)];

        if (selectedMove) {
            this.makeMove(selectedMove.from, selectedMove.to, true);  
            this.isAIMoving = false;
            return { ...selectedMove, isCheckmate: this.isCheckmate };
        }

        this.isAIMoving = false;
        return null;
    }
}

 
let chess = new ChessGame();

 
function initChessBoard(boardState = null, currentTurn = 'white') {
    chess.init(boardState, currentTurn);
    chess.moveCallback = typeof onMoveComplete === 'function' ? onMoveComplete : null;
}

function updateBoardState(boardState, currentTurn = null, lastMove = null) {
    chess.updateBoardState(boardState, currentTurn, lastMove);
}
