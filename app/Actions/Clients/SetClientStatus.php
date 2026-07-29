<?php

namespace App\Actions\Clients;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class SetClientStatus
{
    public function handle(Client $client, RegistrationStatus $status): Client
    {
        return DB::transaction(function () use ($client, $status): Client {
            $client->update([
                'status' => $status,
            ]);

            return $client->refresh();
        });
    }
}
