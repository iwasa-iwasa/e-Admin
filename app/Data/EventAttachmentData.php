<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class EventAttachmentData extends Data
{
    public function __construct(
        public int $attachment_id,
        public int $event_id,
        public string $file_name,
        public string $file_path,
        public ?int $file_size,
        public ?string $mime_type,
        public ?Carbon $uploaded_at,
    ) {
    }
}
