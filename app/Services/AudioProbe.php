<?php

namespace App\Services;

use FFMpeg\FFProbe;
use Throwable;

/**
 * Thin wrapper around ffprobe for reading audio metadata. Extracted from the
 * controller so duration checks can be stubbed in tests without real binaries.
 */
class AudioProbe
{
    /**
     * Probe an audio file's duration in milliseconds, or null if unreadable.
     */
    public function durationMs(string $path): ?int
    {
        try {
            $ffprobe = FFProbe::create([
                'ffmpeg.binaries' => config('media-library.ffmpeg_path'),
                'ffprobe.binaries' => config('media-library.ffprobe_path'),
            ]);

            $duration = $ffprobe->format($path)->get('duration');

            if ($duration === null) {
                return null;
            }

            return (int) round(((float) $duration) * 1000);
        } catch (Throwable $e) {
            return null;
        }
    }
}
