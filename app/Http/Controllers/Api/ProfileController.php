<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return $this->ok($this->payload($user), 'Profile loaded!', [
            'is_disabled' => $user->status !== 'active',
            'is_deleted' => false,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],          // rejects garbage / wrong formats
            'address' => ['nullable', 'string', 'max:255'],
            'adhar_number' => ['nullable', 'digits:12'],      // Aadhaar = 12 digits
            'pan_number' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'], // PAN format
            'profile_image' => ['nullable', 'image', 'max:10240'],
            'adhar_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'pan_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $user = $request->user();
        $gamer = $user->gamer()->firstOrCreate(['user_id' => $user->id]);

        foreach (['name', 'gender', 'date_of_birth', 'address', 'adhar_number', 'pan_number'] as $field) {
            if ($request->filled($field)) {
                $gamer->$field = $data[$field];
            }
        }

        foreach (['profile_image', 'adhar_document', 'pan_document'] as $field) {
            if ($request->hasFile($field)) {
                $gamer->$field = $request->file($field)->store("gamers/{$user->id}", 'public');
            }
        }

        $gamer->save();

        return $this->ok($this->payload($user->fresh()), 'Profile updated successfully!');
    }

    private function payload(User $user): array
    {
        $user->loadMissing('gamer', 'wallet');
        $g = $user->gamer;

        return [
            'id' => $user->id,
            'role' => $user->role,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'status' => $user->status,
            'referral_code' => $user->referral_code,
            'gamer' => $g ? [
                'id' => $g->id,
                'user_id' => $g->user_id,
                'name' => $g->name,
                'gender' => $g->gender,
                'date_of_birth' => $g->date_of_birth,
                'address' => $g->address,
                'adhar_number' => $g->adhar_number,
                'pan_number' => $g->pan_number,
                'adhar_document' => $this->fileUrl($g->adhar_document),
                'pan_document' => $this->fileUrl($g->pan_document),
                'profile_image' => $this->fileUrl($g->profile_image),
                'kyc_status' => $g->kyc_status,
                'total_cash' => (string) ($user->wallet->balance ?? '0.00'),
                'total_bonus' => (string) ($user->wallet->bonus ?? '0.00'),
            ] : null,
        ];
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
