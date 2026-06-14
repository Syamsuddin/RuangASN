<?php

namespace Database\Factories;

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        $contents = [
            'Mohon segera tindak lanjuti disposisi terlampir, terima kasih.',
            'Rapat koordinasi dijadwalkan ulang besok pukul 09.00 WIB.',
            'Sudah saya unggah draft laporan triwulan ke folder bersama.',
            'Baik Pak, akan saya siapkan bahan paparannya.',
            'Tolong dicek kembali nomor surat agar tidak duplikat.',
            'Notulensi rapat kemarin sudah final, silakan ditinjau.',
            'Apakah ada update terkait usulan anggaran perubahan?',
            'Siap, koordinasi dengan Bidang Keuangan sudah dilakukan.',
        ];

        return [
            'id'                  => (string) Str::ulid(),
            'content'             => $this->faker->randomElement($contents),
            'content_type'        => 'text',
            'data_classification' => 3,
            'is_pinned'           => false,
        ];
    }

    public function inChannel(ChatChannel $channel, User $sender): static
    {
        return $this->state([
            'channel_id' => $channel->id,
            'sender_id'  => $sender->id,
        ]);
    }
}
