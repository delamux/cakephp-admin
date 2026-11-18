<?php
declare(strict_types=1);

use Cake\Utility\Text;
use Migrations\BaseSeed;

/**
 * Demo data for development / QA (NOT for production):
 *   docker compose exec app bin/cake seeds run DemoUsers
 *
 * 40 synthetic users spread over the last 90 days (password: Demo1234!).
 */
class DemoUsersSeed extends BaseSeed
{
    /**
     * @inheritDoc
     */
    public function run(): void
    {
        mt_srand(42);
        $first = [
            'Ana', 'Luis', 'Marta', 'Jorge', 'Lucia', 'Pablo', 'Elena', 'Diego',
            'Sara', 'Hugo', 'Irene', 'Mario', 'Clara', 'Raul', 'Nora', 'Ivan',
        ];
        $last = [
            'Garcia', 'Martin', 'Lopez', 'Sanchez', 'Romero', 'Navarro',
            'Torres', 'Ruiz', 'Gil', 'Serrano', 'Molina', 'Ortega',
        ];
        $password = password_hash('Demo1234!', PASSWORD_DEFAULT);
        $now = time();

        $rows = [];
        for ($i = 1; $i <= 40; $i++) {
            $firstName = $first[mt_rand(0, count($first) - 1)];
            $lastName = $last[mt_rand(0, count($last) - 1)];
            // More signups in recent weeks
            $daysAgo = (int)floor(90 * (mt_rand(0, 1000) / 1000) ** 1.7);
            $created = date('Y-m-d H:i:s', $now - $daysAgo * 86400 - mt_rand(0, 86000));
            $active = mt_rand(1, 100) <= 80;

            $rows[] = [
                'id' => Text::uuid(),
                'username' => sprintf('demo.%s%02d', strtolower($firstName), $i),
                'email' => sprintf('demo%02d@example.test', $i),
                'password' => $password,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => match (true) {
                    $i % 13 === 0 => 'admin',
                    $i % 7 === 0 => 'editor',
                    default => 'user',
                },
                'is_superuser' => false,
                'active' => $active,
                'activation_date' => $active ? $created : null,
                'tos_date' => $created,
                'last_login' => $active && mt_rand(0, 1)
                    ? date('Y-m-d H:i:s', $now - mt_rand(0, $daysAgo * 86400 + 1))
                    : null,
                'created' => $created,
                'modified' => $created,
            ];
        }

        $this->table('users')->insert($rows)->saveData();
    }
}
