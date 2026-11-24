The extra template tags an alert gets when it stands for a burst: many of the same event at once.

When two or more follows, subscriptions, resubscriptions, cheers from different viewers or channel point redemptions of one reward arrive within about a second, Overlabels plays ONE alert for all of them instead of one each. The burst stays open while more keep coming, until a gap of four seconds. Every event still counts and still reaches your overlay's event data; only the extra alerts, voice lines and chat lines are merged.

The burst plays your **burst trigger's** alert when you assigned one (Follower Burst, Subscription Burst, Resubscription Burst, Bits Cheer Burst, Channel Points Burst), and your normal alert for that event when you did not. Either way it carries the first event's tags plus these:

### Burst Data
- `[[[event.burst_count]]]` :: How many events the burst held when its alert played, the first one included
- `[[[event.burst_started_at]]]` :: When the burst began, in Unix seconds

All other event tags are the first event's: `[[[event.user_name]]]` is the first follower, `[[[event.bits]]]` the first cheer's bits.

A single event never carries `[[[event.burst_count]]]`, and an empty tag renders as nothing, so one alert can serve both.

example:
```
<div class="follow-alert">
  [[[if:event.burst_count]]]
    [[[event.burst_count]]] new followers!
  [[[else]]]
    [[[event.user_name]]] just followed!
  [[[endif]]]
</div>
```

note: one viewer cheering again and again gets an alert per cheer. Gift bombs, raids and hype trains are already one alert each and never burst. Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne always play one alert per donation.
### tags
#burst #follow-bomb #raid #follower #subscription #cheer #template-tags
