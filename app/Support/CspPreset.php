<?php

namespace App\Support;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class CspPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->addNonce(Directive::SCRIPT)
            ->add(Directive::STYLE, [Keyword::SELF, Keyword::UNSAFE_INLINE])
            ->add(Directive::STYLE, 'fonts.bunny.net')
            ->add(Directive::FONT, [Keyword::SELF, 'fonts.bunny.net'])
            ->add(Directive::IMG, [Keyword::SELF, 'data:', 'media.tenor.com', 'c.tenor.com'])
            ->add(Directive::CONNECT, [Keyword::SELF, 'wss:', 'ws:', 'tenor.googleapis.com'])
            ->add(Directive::FRAME, 'www.youtube.com')
            ->add(Directive::MEDIA, Keyword::SELF)
            ->add(Directive::WORKER, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF);
    }
}
