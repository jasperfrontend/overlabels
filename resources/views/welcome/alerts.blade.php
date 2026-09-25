{{--
  The five alert products, one tile per service, read from the catalogue like
  the games above. Deliberately a quieter row: an alert is a plain thing
  (connect a service, its tips land on stream), so it gets the mark, the name
  and a line, and the product page does the rest.
--}}
@php
    $serviceColors = [
        // The same brand colours useEventColors.ts uses for the events feed.
        'kofi' => 'text-[#ff5a16]',
        'streamlabs' => 'text-[#80f5d2]',
        'fourthwall' => 'text-[#0b48f9]',
        'bmac' => 'text-[#ffdd00]',
        'throne' => 'text-rose-500',
    ];
@endphp
<section id="alerts" class="scroll-mt-16 border-b border-b-sidebar-border bg-card py-20 sm:py-24">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <span class="inline-flex items-center border-transparent bg-accent text-foreground font-semibold transition-colors mb-4 px-3 py-1 font-mono text-xs hover:bg-background-accent">Alerts</span>
      <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Your tips land on stream, whoever sends them.</h2>
      <p class="mb-12 max-w-2xl text-lg text-foreground">
        Connect the service you already use and every tip shows up on your overlay with a sound, a spoken line and
        a message in chat. Five services, one alert each, all in one place.
      </p>

      <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($alerts as $alert)
          @php
              $service = $alert['installs']['integrations'][0] ?? null;
          @endphp
          <li class="collection-row relative flex flex-col gap-3 border border-sidebar-border bg-sidebar-accent p-4">
            <a href="{{ route('products.show', $alert['slug']) }}" class="absolute inset-0 z-0 cursor-pointer" aria-label="{{ $alert['name'] }}"></a>
            @if ($service)
              <span class="{{ $serviceColors[$service] ?? 'text-foreground' }}">
                @include('welcome._service-mark', ['service' => $service, 'class' => 'size-8'])
              </span>
            @endif
            <h3 class="text-base font-semibold">{{ $alert['name'] }}</h3>
          </li>
        @endforeach
      </ul>

      <p class="mt-8 text-xs text-muted-foreground">
        Overlabels is an independent product and is not affiliated with, endorsed by or sponsored by Twitch, Ko-fi,
        Streamlabs, Fourthwall, Buy Me a Coffee or Throne. Their names and marks belong to them.
      </p>
    </div>
  </div>
</section>
