<?php

namespace App\Http\Requests\Panel;

use App\Enums\ContentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentUploadRequest extends FormRequest
{
    private const MAX_FILE_KB = 10240;

    private const MIN_DURATION = 1;

    private const MAX_DURATION = 300;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $raw = $this->input('content_type');
        $type = is_numeric($raw) ? ContentType::tryFrom((int) $raw) : null;
        $isImageOrUrl = in_array($type, [ContentType::Image, ContentType::Url], true);

        return [
            'content_type' => [
                'required',
                'integer',
                Rule::in([
                    ContentType::Image->value,
                    ContentType::Video->value,
                    ContentType::Url->value,
                ]),
            ],

            'file' => [
                Rule::when($type === ContentType::Image, [
                    'required',
                    'file',
                    'max:'.self::MAX_FILE_KB,
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp',
                    'extensions:jpg,jpeg,png,gif,webp',
                ]),
                Rule::when($type === ContentType::Video, [
                    'required',
                    'file',
                    'max:'.self::MAX_FILE_KB,
                    'mimetypes:video/mp4,video/webm,video/quicktime',
                    'extensions:mp4,webm,mov',
                ]),
                Rule::when($type === ContentType::Url, ['prohibited']),
            ],

            'display_time' => [
                Rule::when($isImageOrUrl, [
                    'required',
                    'integer',
                    'min:'.self::MIN_DURATION,
                    'max:'.self::MAX_DURATION,
                ]),
                Rule::when($type === ContentType::Video, ['prohibited']),
            ],

            'url' => [
                Rule::when($type === ContentType::Url, [
                    'required',
                    'string',
                    'max:500',
                    'url:http,https',
                ]),
                Rule::when($type !== ContentType::Url, ['prohibited']),
            ],

            'url_user_name' => [
                Rule::when($type === ContentType::Url, ['nullable', 'string', 'max:255']),
                Rule::when($type !== ContentType::Url, ['prohibited']),
            ],

            'url_password' => [
                Rule::when($type === ContentType::Url, ['nullable', 'string', 'max:255']),
                Rule::when($type !== ContentType::Url, ['prohibited']),
            ],
        ];
    }
}
