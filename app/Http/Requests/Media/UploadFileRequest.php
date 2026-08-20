<?php

namespace App\Http\Requests\Media;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFileRequest extends FormRequest
{
    /** Supported module aliases and their model classes. */
    public const MODULES = [
        'announcement' => Announcement::class,
        'event'        => Event::class,
        'task'         => Task::class,
        'department'   => Department::class,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Allowed MIME types — blocks executables, scripts, and other dangerous
     * content regardless of file extension.  Laravel's `mimetypes:` rule
     * inspects the actual file content via finfo, not just the extension.
     *
     * NOTE: `image/svg+xml` is deliberately NOT allowed. An SVG is an XML
     * document that may contain <script>; when stored on the public disk it is
     * served inline from our own origin, so opening it executes that script
     * with access to the visitor's session — stored XSS. Laravel's own `image`
     * rule excludes SVG by default for the same reason.
     */
    public const ALLOWED_MIME_TYPES = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        // PDF
        'application/pdf',
        // Microsoft Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Microsoft Excel
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Microsoft PowerPoint
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Plain text / CSV
        'text/plain', 'text/csv',
        // Audio
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/aac',
        // Video
        'video/mp4', 'video/quicktime', 'video/mpeg', 'video/webm',
    ];

    /**
     * Allowed *filename* extensions.
     *
     * `mimetypes:` inspects file content and says nothing about the name the
     * file is stored under. A polyglot (valid image magic bytes followed by
     * script source) passes the content check while keeping a hostile
     * extension. Laravel blocks a handful of PHP extensions internally, and the
     * production Nginx config only hands `\.php$` to FPM — but relying on both
     * of those to stay true is fragile. Pinning the extension explicitly means
     * the stored filename can only ever be something we chose.
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv',
        'mp3', 'ogg', 'wav', 'm4a', 'aac',
        'mp4', 'mov', 'mpeg', 'webm',
    ];

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200',  // 50 MB
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                'extensions:' . implode(',', self::ALLOWED_EXTENSIONS),
            ],
            'attachable_type' => ['required', 'string', Rule::in(array_keys(self::MODULES))],
            'attachable_id'   => ['required', 'integer', 'min:1'],
            'is_public'       => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $message = 'The file type is not allowed. Permitted types: images (JPG, PNG, GIF, WebP), '
                 . 'PDF, Office documents, plain text, audio, and video.';

        return [
            'file.mimetypes' => $message,
            'file.extensions' => $message,
        ];
    }

    /**
     * Resolve the Eloquent model from the validated attachable_type + attachable_id.
     * Returns null if the record does not exist.
     */
    public function resolveAttachable(): ?\Illuminate\Database\Eloquent\Model
    {
        $class = self::MODULES[$this->attachable_type] ?? null;

        if (! $class) {
            return null;
        }

        return $class::find($this->attachable_id);
    }
}
