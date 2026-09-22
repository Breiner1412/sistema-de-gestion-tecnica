<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'estado', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROL_ADMIN = 'admin';
    public const ROL_GERENTE = 'gerente';
    public const ROL_CALL_CENTER = 'call_center';
    public const ROL_TECNICO_SOPORTE = 'tecnico_soporte';
    public const ROL_INGENIERO_REDES = 'ingeniero_redes';
    public const ROL_TECNICO_CAMPO = 'tecnico_campo';

    /**
     * Los nombres siguen el flujo real: quien atiende en remoto es el técnico
     * de soporte (nivel 2); los ingenieros son los de redes, que son nivel 3.
     */
    public const ROLES = [
        self::ROL_ADMIN => 'Administrador',
        self::ROL_GERENTE => 'Gerente',
        self::ROL_CALL_CENTER => 'Primer nivel (caja, call center, WhatsApp)',
        self::ROL_TECNICO_SOPORTE => 'Técnico de soporte (nivel 2)',
        self::ROL_INGENIERO_REDES => 'Ingeniero de redes (nivel 3)',
        self::ROL_TECNICO_CAMPO => 'Técnico de campo',
    ];

    /** Roles que pueden gestionar casos en la bandeja. */
    public const ROLES_GESTION = [
        self::ROL_ADMIN,
        self::ROL_GERENTE,
        self::ROL_CALL_CENTER,
        self::ROL_TECNICO_SOPORTE,
        self::ROL_INGENIERO_REDES,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN;
    }

    /** Quien puede quedar como responsable de un caso en el nivel 2. */
    public function scopeTecnicosSoporte(Builder $query): Builder
    {
        return $query->whereIn('rol', [self::ROL_TECNICO_SOPORTE, self::ROL_ADMIN])
            ->where('estado', 'activo')
            ->orderBy('name');
    }

    /** Nivel 3: a quién se le puede escalar. */
    public function scopeIngenierosRedes(Builder $query): Builder
    {
        return $query->where('rol', self::ROL_INGENIERO_REDES)
            ->where('estado', 'activo')
            ->orderBy('name');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'activo');
    }

    public function tecnico()
    {
        return $this->hasOne(Tecnico::class);
    }

    public function soportesAsignados()
    {
        return $this->hasMany(Soporte::class, 'tecnico_soporte_id');
    }

    public function soportesEscalados()
    {
        return $this->hasMany(Soporte::class, 'escalado_a_id');
    }

    public function etiquetaRol(): string
    {
        return self::ROLES[$this->rol] ?? $this->rol;
    }
}
