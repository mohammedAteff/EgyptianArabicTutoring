<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('lms:video:prune-security {--limit=500 : Maximum Students to clean in this run}')]
#[Description('Expire abandoned playback leases and remove old security records without learning telemetry')]
class PruneLmsVideoSecurity extends Command
{
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
        if ($limit === false) {
            $this->error('Choose a limit from 1 to 5000.');

            return self::FAILURE;
        }
        $expiredDevices = DB::table('lms_authorized_devices')->where('status', 'revoked')->where('revoked_at', '<', now('UTC')->subDays(366))->select('student_id');
        $ids = DB::table('lms_playback_leases')->where(fn ($query) => $query
            ->where(fn ($query) => $query->where('status', 'active')->where('expires_at', '<=', now('UTC')))
            ->orWhere(fn ($query) => $query->where('authorized_until', '<', now('UTC')->subDays(7))->where('expires_at', '<', now('UTC')->subDays(7))))
            ->select('student_id')->union($expiredDevices)->distinct()->orderBy('student_id')->limit($limit)->pluck('student_id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                DB::table('students')->where('id', $id)->lockForUpdate()->first(['id']);
                DB::table('lms_playback_leases')->where('student_id', $id)->where('status', 'active')->where('expires_at', '<=', now('UTC'))->update(['status' => 'expired']);
                DB::table('lms_playback_leases')->where('student_id', $id)->where('authorized_until', '<', now('UTC')->subDays(7))->where('expires_at', '<', now('UTC')->subDays(7))->delete();
                DB::table('lms_authorized_devices')->where('student_id', $id)->where('status', 'revoked')->where('revoked_at', '<', now('UTC')->subDays(366))
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('lms_playback_leases')->whereColumn('device_id', 'lms_authorized_devices.id'))->delete();
            }, 3);
        }
        DB::table('lms_video_webhook_receipts')->whereIn('id', DB::table('lms_video_webhook_receipts')->where('received_at', '<', now('UTC')->subDays(30))->orderBy('id')->limit(1000)->pluck('id'))->delete();
        $this->info('Expired playback sessions cleaned; no learning progress was recorded.');

        return self::SUCCESS;
    }
}
