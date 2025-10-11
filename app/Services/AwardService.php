<?php

namespace App\Services;

use App\Models\Application;
use App\Models\StudentNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AwardService
{
    public function onApproved(Application $app): void
    {
        // Save certificate to public storage (if your PDF is in DB blob)
        $disk = Storage::disk('public');
        $dir  = "awards/{$app->Student_id}";
        $disk->makeDirectory($dir);

        $certName = "deans-certificate-{$app->Application_id}.pdf";
        if (!empty($app->File_data)) {
            $disk->put("$dir/$certName", $app->File_data);
        }

        // Create award notification
        StudentNotification::create([
            'Student_id'  => $app->Student_id,
            'type'        => 'deans_lister_award',
            'title'       => "Dean’s Lister Award",
            'message'     => "You qualified for Dean’s Lister. Tap to claim your badge and certificate.",
            'data'        => [
                'application_id'   => $app->Application_id,
                'certificate_path' => "$dir/$certName",
                'badge_path'       => 'assets/badges/deans_lister.png',
            ],
            'claim_token' => Str::random(40),
            'claimable'   => true,
            'is_read'     => false,
        ]);
    }
}
