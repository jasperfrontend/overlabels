{{--
  Where a product runs, and for a product that reads emotes off chat, which
  emote services it draws. Two <x-brand-icons> groups, each its title over its
  own row of tiles, side by side where they fit. Only apps a browser source
  overlay is known to work in: a new one goes here once a product has been
  seen running in it.

  Usage: @include('products._works-with', ['class' => 'mt-12'])
--}}
<div class="flex flex-wrap gap-x-10 gap-y-6 {{ $class ?? '' }}">
    <x-brand-icons title="Works with" :icons="['twitch', 'obs', 'streamlabs']" />
    @if (in_array($product['slug'], ['twitch-chat-overlay', 'chat-emote-bubbles'], true))
        <x-brand-icons title="Shows emotes from" :icons="['7tv', 'bttv', 'ffz']" />
    @endif
</div>
