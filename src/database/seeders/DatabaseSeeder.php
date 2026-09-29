<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AccessRule;
use App\Models\Building;
use App\Models\Turnstile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Edificios y torniquetes (uno de entrada y uno de salida) ----
        $buildings = [];
        $names = [
            'BIB' => 'Biblioteca Central',
            'ING' => 'Edificio de Ingeniería',
            'SAL' => 'Ciencias de la Salud',
            'ADM' => 'Edificio Administrativo',
        ];

        foreach ($names as $code => $name) {
            $buildings[$code] = Building::create(['code' => $code, 'name' => $name]);

            foreach (['in' => 'IN', 'out' => 'OUT'] as $direction => $suffix) {
                Turnstile::create([
                    'building_id' => $buildings[$code]->id,
                    'code' => "T-{$code}-{$suffix}-01",
                    'direction' => $direction,
                    'status' => 'active',
                ]);
            }
        }

        // ---- Reglas de acceso ----
        $rule = fn (string $b, ?string $role, ?string $program, array $days, string $from, string $to) =>
            AccessRule::create([
                'building_id' => $buildings[$b]->id,
                'role' => $role,
                'program' => $program,
                'days_of_week' => $days,
                'start_time' => $from,
                'end_time' => $to,
                'is_active' => true,
            ]);

        $monFri = [1, 2, 3, 4, 5];
        $monSat = [1, 2, 3, 4, 5, 6];
        $allWeek = [1, 2, 3, 4, 5, 6, 7];

        // Estudiantes
        $rule('BIB', 'student', null, $monSat, '06:00', '21:00');
        $rule('ING', 'student', 'ingenieria', $monFri, '06:00', '22:00');
        $rule('SAL', 'student', 'medicina', $monFri, '06:00', '20:00');

        // Docentes: todos los edificios académicos
        foreach (['BIB', 'ING', 'SAL'] as $b) {
            $rule($b, 'teacher', null, $monSat, '05:00', '22:00');
        }

        // Administrativos
        $rule('ADM', 'staff', null, $monFri, '07:00', '18:00');
        $rule('BIB', 'staff', null, $monFri, '07:00', '18:00');

        // Admin: todo, siempre
        foreach (array_keys($buildings) as $b) {
            $rule($b, 'admin', null, $allWeek, '00:00', '23:59');
        }

        // ---- Usuarios de prueba (contraseña: "password") ----
        User::unguarded(function () {
            $users = [
                ['Admin Sistema',  'admin@uni.test',   UserRole::Admin,   null,       null],
                ['Carlos Ruiz',    'docente@uni.test', UserRole::Teacher, null,       null],
                ['Marta Gómez',    'staff@uni.test',   UserRole::Staff,   null,       null],
                ['Ana Torres',     'ana@uni.test',     UserRole::Student, 'ingenieria', '20261001'],
                ['Luis Pérez',     'luis@uni.test',    UserRole::Student, 'medicina',   '20261002'],
                ['Sofía Ramírez',  'sofia@uni.test',   UserRole::Student, 'derecho',    '20261003'],
            ];

            foreach ($users as [$name, $email, $role, $program, $code]) {
                User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => 'password',
                    'role' => $role->value,
                    'program' => $program,
                    'student_code' => $code,
                    'is_active' => true,
                ]);
            }

            // Estudiante inactivo, para probar el rechazo
            User::create([
                'name' => 'Pedro Inactivo',
                'email' => 'pedro@uni.test',
                'password' => 'password',
                'role' => UserRole::Student->value,
                'program' => 'ingenieria',
                'student_code' => '20261004',
                'is_active' => false,
            ]);
        });
    }
}
