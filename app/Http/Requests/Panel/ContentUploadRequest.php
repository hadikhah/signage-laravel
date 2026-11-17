<?php

namespace App\Http\Requests\Panel;

use App\Enums\ContentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentUploadRequest extends FormRequest
{

    private const MAX_FILE_KB = 10240;

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
        $type = $this->input('content_type');

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
                Rule::when($type === ContentType::Image->value, [
                    'required',
                    'file',
                    'max:' . self::MAX_FILE_KB,
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp'
                ]),
                Rule::when($type === ContentType::Video->value, [
                    'required',
                    'file',
                    'max:' . self::MAX_FILE_KB,
                    'mimes:mp4,webm,mov',
                ]),
                Rule::when($type === ContentType::Url->value, ['prohibited']),
            ],

            'url' => [
                Rule::when($type === ContentType::Url->value, [
                    'required',
                    'string',
                    'max:500',
                    'url:http,https',
                ]),
                Rule::when($type !== ContentType::Url->value, ['prohibited']),
            ],

            'url_user_name' => [
                Rule::when($type === ContentType::Url->value, ['nullable', 'string', 'max:255']),
                Rule::when($type !== ContentType::Url->value, ['prohibited']),
            ],

            'url_password' => [
                Rule::when($type === ContentType::Url->value, ['nullable', 'string', 'max:255']),
                Rule::when($type !== ContentType::Url->value, ['prohibited']),
            ],
        ];
    }
}
