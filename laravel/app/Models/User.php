<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mongo\DocumentStore;
use App\Mongo\Schema;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use MongoDB\Laravel\Auth\User as Authenticatable;
use MongoDB\Laravel\Query\Builder;

#[Fillable(['name', 'username', 'email', 'phone', 'address', 'role', 'status', 'password'])]
#[Hidden(['password', 'remember_token', '_search_name'])]
class User extends Authenticatable
{
    protected $connection = 'mongodb';

    protected $primaryKey = '_id';

    protected $keyType = 'int';

    public $incrementing = false;

    protected $attributes = [
        'role' => 'customer', 'status' => 'active', 'failed_logins' => 0,
        'locked_until' => null, 'username' => null, 'phone' => null,
        'email' => null, 'address' => null, 'email_verified_at' => null, 'remember_token' => null,
    ];

    protected function newBaseQueryBuilder(): Builder
    {
        return parent::newBaseQueryBuilder()->options(['collation' => Schema::COLLATION]);
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->setAttribute('_search_name', Str::lower(Str::ascii((string) $user->name)));
        });
        static::creating(function (self $user): void {
            $store = new DocumentStore($user->getConnection());
            $user->setAttribute('_id', $user->getAttribute('_id') ?? $store->nextId('users'));
        });
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'failed_logins' => 'integer',
            '_id' => 'integer',
        ];
    }
}
