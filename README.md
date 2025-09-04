# Welcome to Overlabels

Overlabels is a dead-simple approach to OBS overlays. Where other providers offer a drag and drop editor, I figured: we can just write these in HTML and CSS. So with that idea in mind, Overlabels was born.

## Just... HTML and CSS?
Well yes, and no. It would be very nice if you could actually show your Twitch data in your OBS overlays, so I created a very easy to use template syntax. It's not pretty, but it's very functional and no other template parser will ever interfere with this. As I said here:

https://x.com/xoverlabels/status/1963386310783303976

The Overlabels triple-bracket syntax is ugly, but functional. In fact it's:

Too ugly for anyone to accidentally conflict with
Too simple for anyone to not understand
Too portable for anyone to complain about

There are 2 different types of Template Syntax, based on two different Twitch APIs.

1. The mostly static data, taken from the Twitch Helix API.
2. Current events, taken from the Twitch EventSub API.

The Helix data is perfect to create HTML/CSS overlays with for your stream. Show your current follower count, subscriber count, latest follower, latest subscriber, the current category you're streaming in and - since it's just HTML - any other static data you want to show.

### A simple example
```html
    <div class="stat-item">
        <span class="stat-number">[[[followers_total]]]</span>
        <span class="stat-label">Followers</span>
    </div>
```
Rendered this will output: 1300 followers.

### Another example
```html
[[[if:channel_language = en]]]
  <p>Welcome to our English stream!</p>
[[[elseif:channel_language = es]]]
  <p>¡Bienvenidos a nuestro stream en Español!</p>
[[[endif]]] 
```
Here we introduce the Conditional Template Syntax. This is a rather powerful comparison engine that can compare boolean, numerical and string values. The syntax follows a logical structure and any template tag that ouputs a value can be used as a Conditional.

[Read more about Conditional Template Syntax](https://overlabels.com/help) on the website.

Conditional Template Syntax can be used on your Static Overlays, as well as your Event Alert Templates.

## How does a Static Overlay work?
I think the best way to show you how it works, is to show you an actual Static Overlay.

[https://overlabels.com/overlay/tame-rolling-house-full-eagle/public](https://overlabels.com/overlay/tame-rolling-house-full-eagle/public)

This template shows the syntax used to get the Helix data to your frontend.

Do note the `/public` URL here. If you want to actually use this template with your Twitch data, you need to generate a Token.
Tokens are a safe way to retrieve your Twitch data. Tokens are never sent to the backend and aren't stored in a database. Some fine encryption happens on the frontend using Laravel's [CSRF protection](https://laravel.com/docs/12.x/csrf) before your key is sent to the backend to retrieve your data. Frontend queries for your Twitch data are performed by Axios and not by the Laravel backend at all.

If you want to activate this template with your own data, replace public with `/#your_token_here`. Generate a token on `/tokens` in the app.

## KEEP THIS TOKEN A SECRET. TREAT IT LIKE A PASSWORD AND NEVER SHARE IT ON STREAM OR WITH ANYONE WHATSOEVER.
If you suspect your token is leaked, revoke and delete it immediately and create a new token. Then replace the obsolete token with the newly generated one in the overlay URLs in your OBS or wherever you use the Overlabels overlays.

## You need a static overlay to make dynamic Overlay Alerts work
Dynamic overlays live "inside" your static overlays. they can't be fired unless you have a frontend to show them in.

## How do dynamic overlays work?
When you create a new template in the Overlabels webapp, you can choose between a Static Overlay or an Event Alert.
Event Alerts parse a specific syntax per event. Check the [Event-based Template Tags help](https://overlabels.com/help). Every event current integrated into Overlabels has its own syntax. There's a big overlap in most of the EventSub events, but do study the Help documentation thoroughly to find all syntax you can use.

## How do I assign Event Alert templates to alerts?
In the very easy to use Alerts Builder you can assign Twitch EventSub events to different templates, set how long you want to show the alert and choose a transition effect that currenly doesn't work yet.

Once you have created a Template, created Event Alert templates and assigned every template to every event, you're ready to go. Just include the static overlay URL **including** the secure token in your OBS, set the dimensions of the overlay to fullscreen (ctrl-s in OBS) and whenever you receive a new event, your alert template will show and the revelant part of your static template is automatically updated. So if you receive a new follower, your follower count in your template is updated.

All in all, this is incredibly powerful but still a mess. It works, it's safe but it's still very much just an MVP.

If you have questions, please submit a pull request or open an issue. I love to hear from you.
