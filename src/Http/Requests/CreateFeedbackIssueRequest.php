<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

final class CreateFeedbackIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['required', 'string', Rule::in(['bug', 'feature', 'feedback'])],
            'screenshot' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'title.required' => 'An issue title is required.',
            'title.max' => 'The title is too long. Please keep it under 255 characters.',
            'body.required' => 'An issue body is required.',
            'body.max' => 'The body is too long. Please keep it under 10,000 characters.',
            'type.required' => 'A feedback type is required.',
            'type.in' => 'Invalid feedback type.',
        ];
    }
}
