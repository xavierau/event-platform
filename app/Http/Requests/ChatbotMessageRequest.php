<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotMessageRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:5000',
            'session_id' => 'required|string',
            'current_url' => 'nullable|url',
            'page_content' => 'nullable|string|max:50000',
        ];
    }

    /**
     * Get the sanitized message.
     */
    public function sanitizedMessage(): string
    {
        return strip_tags($this->input('message'));
    }
}
