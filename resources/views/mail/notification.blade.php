<x-mail::message>
# {{ $title }}

{{ $body }}

@if ($actionUrl)
<x-mail::button :url="$actionUrl" color="primary">
Buka Notifikasi
</x-mail::button>
@endif

---

Notifikasi ini dikirimkan secara otomatis oleh sistem **RuangASN** — Digital Workspace untuk Aparatur Sipil Negara.

Jika Anda tidak ingin menerima email ini, Anda dapat mengubah preferensi notifikasi di pengaturan akun Anda.

Salam,<br>
Tim RuangASN
</x-mail::message>
