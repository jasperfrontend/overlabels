# CHANGELOG
## February 17th, 2026
- Implemented a massive onboarding system for new users.
- Trying to fix the bug where alerts don't show more than once.
- Added a Twitch CLI test endpoints explanation page.
## February 21st, 2026
- Implemented Controls.
  Users can now create single-purpose data stores as string, number, countup, countdown, datetime, or boolean. The implementation is portable, forkable, unique per overlay, very easy to use, and on top of it all, the new Controls also work well with the comparison engine baked into Overlabels already. This means you can now compare values you set and manipulate yourself without having to dive back into your code to make changes. [Read more](https://overlabels.com/help/controls).
- Any HTML, CSS, or HEAD change to an overlay triggers a hard refresh of the overlay now. This removes any issues with cached data being left behind when embedding this overlay in OBS.
- The Dashboard and Template Details screen had a visual update which makes them more coherent and easier to understand.
- I ditched all Card layouts in favour of tables wherever it made sense. A list of stored overlays is tabular data after all, no matter how you look at it.
- I re-organised the main sidebar navigation, so it's easier to understand where you need to go. I also removed the menu items you don't really need and added those in a "Debug" menu in your profile link.
- I removed the whole community part of Overlabels because its implementation was awful, to say the least. I will work on a better implementation of the Community part of Overlabels, but this is honestly not a top priority right now. You can still view all public overlays on the "All overlays" page.
- The Overlabels Dashboard is still in no way, shape, or form responsive – but at least the Events view is now responsive. The new "Events view" offers a simple way for streamers to see an overview of the latest alerts and the option to replay them on your overlay. You can find this overview under "My activity" in the Overlabels Dashboard.
