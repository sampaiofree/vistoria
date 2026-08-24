<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BootstrapSuperAdmin extends Command
{
    protected $signature = 'app:bootstrap-super-admin
        {--email=sampaio.free@gmail.com : E-mail do superadministrador mestre}
        {--name=Administrador Master : Nome exibido para o superadministrador mestre}';

    protected $description = 'Cria o superadministrador mestre de produção com uma senha temporária';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('name'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('O e-mail informado é inválido.');

            return self::INVALID;
        }

        if ($name === '') {
            $this->error('O nome informado não pode ficar vazio.');

            return self::INVALID;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            if ($existingUser->isSuperAdmin() && $existingUser->organization_id === null) {
                $this->info("O superadministrador {$email} já está configurado. Nenhuma alteração foi feita.");

                return self::SUCCESS;
            }

            $this->error("O e-mail {$email} já pertence a uma conta incompatível. Nenhuma alteração foi feita.");

            return self::FAILURE;
        }

        $temporaryPassword = Str::password(24);

        DB::transaction(function () use ($email, $name, $temporaryPassword): void {
            User::query()->create([
                'organization_id' => null,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'account_type' => UserAccountType::SuperAdmin->value,
                'status' => UserStatus::Active->value,
            ]);
        });

        $this->info("Superadministrador {$email} criado com sucesso.");
        $this->line('Senha temporária: '.$temporaryPassword);
        $this->warn('A senha acima será exibida somente nesta execução. Troque-a no primeiro acesso.');

        return self::SUCCESS;
    }
}
