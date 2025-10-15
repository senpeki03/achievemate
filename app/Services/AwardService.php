<?php

namespace App\Services;

use App\Models\Application;
use App\Models\StudentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AwardService
{
    /**
     * Called ONLY by the DEAN flow when status becomes "Approved".
     * - Generates the certificate PNG (or uses placeholder)
     * - Upserts exactly one 'deans_lister_award' notification per (student, application)
     * Returns the notification (with claim_token).
     */
    public function onApproved(Application $app): StudentNotification
    {
        // 1) Build cert data from student profile
        $builder = app(\App\Services\DeanCertDataBuilder::class);
        $data = $builder->buildFromStudent((int) $app->Student_id, [
            'template_png' => public_path('img/cert/dean-template.png'),
            'out_rel'      => "certificates/deans_lister_app_{$app->Application_id}.png",
            'app_id'       => (int) ($app->Application_id ?? $app->id),
        ]);

        // 2) Render PNG
        $relCert = app(\App\Services\CertificateImageService::class)->makeDeansCertPng($data);

        // Fallback to placeholder image if generation failed
        if (!$relCert) {
            $relCert = 'certificates/deans_lister_placeholder.png';
            $disk = Storage::disk('public');
            if (!$disk->exists($relCert)) {
                $disk->put($relCert, file_get_contents(public_path('img/cert/dean-template.png')));
            }
        }

        // 3) Upsert 1 award-notification per (student, application)
        $payload = [
            'application_id'   => $app->Application_id ?? $app->id,
            'certificate_path' => $relCert,
            'badge_path'       => 'assets/badges/deans_lister.png',
        ];

        $notif = StudentNotification::where('Student_id', $app->Student_id)
            ->where('type', 'deans_lister_award')
            ->where('data->application_id', $payload['application_id'])
            ->first();

        if ($notif) {
            $notif->title      = "Dean’s Lister — Certificate & Badge";
            $notif->message    = "Your Dean’s Lister certificate is ready. Tap to claim.";
            $notif->data       = $payload;
            $notif->claimable  = true;
            $notif->is_read    = false;
            $notif->save();
        } else {
            $notif = StudentNotification::create([
                'Student_id'  => $app->Student_id,
                'type'        => 'deans_lister_award',
                'title'       => "Dean’s Lister — Certificate & Badge",
                'message'     => "Your Dean’s Lister certificate is ready. Tap to claim.",
                'data'        => $payload,
                'claim_token' => Str::uuid()->toString(),
                'claimable'   => true,
                'is_read'     => false,
            ]);
        }

        Log::info('AwardService: deans_lister_award upserted', [
            'student_id' => $app->Student_id,
            'application_id' => $payload['application_id'],
            'notification_id' => $notif->StudentNotification_id ?? null,
        ]);

        return $notif;
    }
}
