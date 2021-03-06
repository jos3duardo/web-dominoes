<?php

namespace App\Http\Controllers;

use App\Game;
use App\Player;
use App\Bone;
use App\User;
use \stdClass;
use App\Services\GameCenterService;
use App\Models\PlayerInfo;
use App\Models\Tree;
use App\Models\Node;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    protected $gameCenter;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(GameCenterService $gameCenter)
    {
        $this->middleware('auth');
        $this->gameCenter = $gameCenter;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $firstUser = $request->input('first_user');
        $secondUser = $request->input('second_user');

        // Check if a *unfinished* game involving the two users already exists

        $players = Player::whereIn('user_id', [ $firstUser, $secondUser ])->pluck('id')->toArray();
        $game = Game::where('is_finished', false)
                    ->whereIn('player_1_id', $players)
                    ->whereIn('player_2_id', $players)
                    ->whereRaw('player_1_id != player_2_id')
                    ->first();

        if (!is_null($game)) {
            // If it does, just redirect to it
            return redirect()->route('game', ['id' => $game->id]);
        } else {
            // If it doesn't, create it (and the Players) and redirect to it

            // Create players here
            $firstPlayer = Player::create([
                'user_id' => $firstUser,
                'current_score' => 0,
                'is_winner' => false
            ]);

            $secondPlayer = Player::create([
                'user_id' => $secondUser,
                'current_score' => 0,
                'is_winner' => false
            ]);

            $id = Game::create([
                'player_1_id' => $firstPlayer->id,
                'player_2_id' => $secondPlayer->id
            ]);

            return redirect()->route('game', ['id' => $id]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Game  $game
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $isUserAllowed = $this->gameCenter->isUserPlayingGame(Auth::id(), $id);

        if (!$isUserAllowed) {
            return redirect()->route('home');
        }

        //$this->gameCenter->destroy($id, $request);
        $gameState = $this->gameCenter->get($id, $request);
        //echo json_encode($gameState);
        //dump($gameState);

        return view('game.show', compact('gameState'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Game  $game
     * @return \Illuminate\Http\Response
     */
    public function edit(Game $game)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Game  $game
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Game $game)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Game  $game
     * @return \Illuminate\Http\Response
     */
    public function destroy(Game $game)
    {
        //
    }

    public function submitTurn(Request $request, $id)
    {
        // Authorize the user doing the turn submission
        $isUserAllowed = $this->gameCenter->isUserPlayingGame(Auth::id(), $id);
        if (!$isUserAllowed) {
            return redirect()->route('home');
        }

        // Get the Move object from the POST request
        $move = $request->all();

        // Get the current game state
        $gameState = $this->gameCenter->get($id, $request);

        // Validate input
        if ($gameState->isMoveValid(Auth::user()->username, $move)) {
            // Update board
            $gameState->updateGameState(Auth::user()->username, $move);
            $gameState = $this->gameCenter->save($id, $gameState, $request);
            return json_encode(true);
        }
        return json_encode(false);
    }

    public function checkTurn(Request $request, $id)
    {
        // Authorize the user doing the turn submission
        $isUserAllowed = $this->gameCenter->isUserPlayingGame(Auth::id(), $id);
        if (!$isUserAllowed) {
            return redirect()->route('home');
        }

        // Get the current game state
        $gameState = $this->gameCenter->get($id, $request);

        // Figure out if it's the user's turn
        $username = Auth::user()->username;
        if ($gameState->currentPlayer === $username) {
            return json_encode(true);
        } else {
            return json_encode(false);
        }
    }

    public function retrieveOngoing() {
        // Get the current user
        $user = Auth::user();

        // Get all the Players associated with that user
        $players = Player::where('user_id', $user->id)->pluck('id')->toArray();

        //dump($players);
        // Get all the currently unfinished games with any of those players
        $games = Game::where('is_finished', false)
                    ->where(function ($query) use ($players) {
                        $query->whereIn('player_1_id', $players)
                            ->orWhereIn('player_2_id', $players);
                    })->whereRaw('player_1_id != player_2_id')->get();
        //dump($games);
        $collection = collect();
        foreach ($games as $game) {
            $obj = new stdClass;
            $obj->gameId = $game->id;

            $user1Id = Player::find($game->player_1_id)->user_id;
            $user2Id = Player::find($game->player_2_id)->user_id;

            $user1 = User::find($user1Id);
            $user2 = User::find($user2Id);

            $obj->player1Username = $user1->username;
            $obj->player2Username = $user2->username;
            $collection->push($obj);
        }
        Log::info('Games collection: ', ['games' => $collection]);
        return json_encode($collection);
    }
}
