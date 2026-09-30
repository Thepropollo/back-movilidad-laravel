<?php

namespace Domain\Auth\Support;

final class RoleCatalog
{
    public const SECRETARIA = 'secretaria';

    public const CONDUCTOR = 'conductor';

    public const MECANICO = 'mecanico';

    public const DOCENTE = 'docente';

    public const RESPONSABLE_FACULTAD = 'responsable_facultad';

    public const VICERRECTOR = 'vicerrector';

    public const ESTUDIANTE = 'estudiante';

    /** @var array<string, string> */
    public const ALIASES = [
        'jefe_transporte' => self::SECRETARIA,
        'chofer' => self::CONDUCTOR,
        'solicitante' => self::DOCENTE,
        'rector' => self::VICERRECTOR,
        'pasajero' => self::ESTUDIANTE,
    ];

    public static function canonicalize(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return self::ALIASES[$role] ?? $role;
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function canonicalizeMany(array $roles): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (?string $role) => self::canonicalize($role),
            $roles
        ))));
    }

    /**
     * Replace only the primary role and retain any secondary roles.
     *
     * @param  list<string>  $current
     * @return list<string>
     */
    public static function replacePrimaryRole(array $current, ?string $currentPrimary, string $nextPrimary): array
    {
        $currentPrimary = self::canonicalize($currentPrimary);
        $next = array_values(array_filter(
            self::canonicalizeMany($current),
            fn (string $role) => $role !== $currentPrimary
        ));
        $next[] = $nextPrimary;

        return self::canonicalizeMany($next);
    }

    /**
     * Mechanic-only users cannot receive conductor. Conductor may gain mechanic.
     *
     * @param  list<string>  $current
     * @param  list<string>  $next
     */
    public static function canAssign(array $current, array $next): bool
    {
        $current = self::canonicalizeMany($current);
        $next = self::canonicalizeMany($next);

        $wasMechanicOnly = in_array(self::MECANICO, $current, true)
            && ! in_array(self::CONDUCTOR, $current, true);
        $nextHasConductor = in_array(self::CONDUCTOR, $next, true);

        return ! ($wasMechanicOnly && $nextHasConductor);
    }
}
