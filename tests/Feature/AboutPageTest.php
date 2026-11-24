<?php

// /about is where someone approached by a fake "Overlabels employee" checks the
// claim, so it must stay public, crawlable and name the one real contact.

it('serves a public about page naming the only Overlabels contact', function () {
    $this->get('/about')
        ->assertOk()
        ->assertSee('Who makes Overlabels')
        ->assertSee('jasperdiscovers')
        ->assertSee('https://twitch.tv/jasperdiscovers', false)
        ->assertSee('<link rel="canonical" href="'.url('/about').'" />', false);
});

it('is linked from the site nav and footer, and listed in the sitemap', function () {
    $home = $this->get('/')->assertOk();
    expect(substr_count($home->getContent(), 'href="'.route('about').'"'))->toBeGreaterThanOrEqual(3);

    $this->get('/sitemap.xml')->assertOk()->assertSee('/about</loc>', false);
});
