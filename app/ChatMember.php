<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChatMember extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chat_id', 'user_id', 'status', 'resource_id',
    ];

    /**
     * Get the chat that the user is a member of.
     */
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that owns the class.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
