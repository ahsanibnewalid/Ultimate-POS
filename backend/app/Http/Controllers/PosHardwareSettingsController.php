<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosHardwareSettingsController
{
    public function show(Request $request)
    {
        $settings = DB::table('tenant_pos_settings')->where('tenant_id', (int) $request->user()->tenant_id)->first();
        return response()->json(['settings' => [
            'printer_mode' => $settings->printer_mode ?? 'browser',
            'paper_width_mm' => (int) ($settings->paper_width_mm ?? 80),
            'barcode_scanner_enabled' => (bool) ($settings->barcode_scanner_enabled ?? true),
            'cash_drawer_enabled' => (bool) ($settings->cash_drawer_enabled ?? false),
            'terminal_mode' => $settings->terminal_mode ?? 'standalone',
            'receipt_footer' => $settings->receipt_footer ?? 'Thank you for your purchase!',
        ]]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only the workspace administrator can change POS hardware settings.');
        $data = $request->validate([
            'printer_mode' => ['required', 'in:browser,network_agent'],
            'paper_width_mm' => ['required', 'integer', 'in:58,80'],
            'barcode_scanner_enabled' => ['required', 'boolean'],
            'cash_drawer_enabled' => ['required', 'boolean'],
            'terminal_mode' => ['required', 'in:standalone,disabled'],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
        ]);
        DB::table('tenant_pos_settings')->updateOrInsert(
            ['tenant_id' => (int) $request->user()->tenant_id],
            [
                'printer_mode' => $data['printer_mode'], 'paper_width_mm' => $data['paper_width_mm'],
                'barcode_scanner_enabled' => $data['barcode_scanner_enabled'],
                'cash_drawer_enabled' => $data['cash_drawer_enabled'], 'terminal_mode' => $data['terminal_mode'],
                'receipt_footer' => $data['receipt_footer'] ?? '', 'updated_at' => now(), 'created_at' => now(),
            ]
        );
        return $this->show($request);
    }
}
