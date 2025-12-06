<?php

namespace App\Http\Controllers;

use App\Models\CreamTransfer;
use App\Models\CreamDailyControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreamTransferController extends Controller
{
    public function show(CreamTransfer $transfer)
    {
        // Seguridad: solo quien RECIBE puede ver esto
        if ($transfer->to_comercial_id !== Auth::id()) {
            abort(403);
        }

        return view('cream-transfers.show', [
            'transfer' => $transfer->load(['fromComercial', 'toComercial']),
        ]);
    }

    public function accept(CreamTransfer $transfer)
    {
        if ($transfer->to_comercial_id !== Auth::id()) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            abort(400);
        }

        $today = Carbon::today()->toDateString();

        DB::transaction(function () use ($transfer, $today) {

            $toControl = CreamDailyControl::lockForUpdate()
                ->where('comercial_id', $transfer->to_comercial_id)
                ->whereDate('date', $today)
                ->firstOrFail();

            $fromControl = CreamDailyControl::lockForUpdate()
                ->where('comercial_id', $transfer->from_comercial_id)
                ->whereDate('date', $today)
                ->firstOrFail();

            // Validación final
            if ($toControl->remaining < $transfer->amount) {
                throw new \Exception("Ya no tienes suficientes cremas para donar.");
            }

            // Ajustes
            $toControl->donated += $transfer->amount;
            $fromControl->received += $transfer->amount;

            $toControl->save();
            $fromControl->save();

            $transfer->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
        });

        Notification::make()
            ->title('Transferencia aceptada')
            ->success()
            ->send();

        return redirect()->route('filament.comercial.pages.dashboard');
    }

    public function reject(CreamTransfer $transfer)
    {
        if ($transfer->to_comercial_id !== Auth::id()) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            abort(400);
        }

        $transfer->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        Notification::make()
            ->title('Solicitud rechazada')
            ->warning()
            ->send();

        return redirect()->route('filament.comercial.pages.dashboard');
    }
}
