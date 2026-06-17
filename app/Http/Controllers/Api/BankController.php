<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BankController extends Controller
{
    public function show(Request $request)
    {
        $bank = $request->user()->bankDetail;

        return $this->ok($bank ? $this->payload($bank) : null, 'Bank details loaded!', [
            'is_disabled' => false,
            'is_deleted' => false,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'account_holder_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $user = $request->user();
        $bank = $user->bankDetail()->firstOrCreate(['user_id' => $user->id]);

        foreach (['name', 'branch', 'account_holder_name', 'account_number', 'ifsc_code', 'upi_id'] as $field) {
            if ($request->filled($field)) {
                $bank->$field = $data[$field];
            }
        }

        if ($request->hasFile('document')) {
            $bank->document = $request->file('document')->store("bank/{$user->id}", 'public');
        }

        $bank->save();

        return $this->ok($this->payload($bank), 'Bank details updated successfully!');
    }

    private function payload(BankDetail $b): array
    {
        return [
            'id' => $b->id,
            'user_id' => $b->user_id,
            'name' => $b->name,
            'branch' => $b->branch,
            'account_holder_name' => $b->account_holder_name,
            'account_number' => $b->account_number,
            'ifsc_code' => $b->ifsc_code,
            'upi_id' => $b->upi_id,
            'document' => $b->document ? Storage::disk('public')->url($b->document) : null,
            'status' => $b->status,
        ];
    }
}
