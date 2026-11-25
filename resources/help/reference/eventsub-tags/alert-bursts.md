The extra template tags an alert gets when it stands for a burst: many of the same event at once.

When two or more follows, subscriptions, resubscriptions, cheers from different viewers or channel point redemptions of one reward arrive within about a second, Overlabels plays ONE alert for all of them instead of one each. The burst stays open while more keep coming, until a gap of four seconds. Every event still counts and still reaches your overlay's event data; only the extra alerts, voice lines and chat lines are merged.
A long burst is announced again with its running count: each time the count has at least doubled since its last alert, and at least 15 seconds after it. A raid's follow wave plays "20 new followers", later "160", later "1300", not one alert at the start and silence after.

The burst plays your **burst trigger's** alert when you assigned one (Follower Burst, Subscription Burst, Resubscription Burst, Bits Cheer Burst, Channel Points Burst), and your normal alert for that event when you did not. Either way it carries that event's tags plus these:

### Burst Data
- `[[[event.burst_count]]]` :: How many events the burst held when this alert played, the first one included. Grows with each announcement of the same burst
- `[[[event.burst_started_at]]]` :: When the burst began, in Unix seconds. The same for every announcement of one burst

All other event tags are the announcing event's: the first event's for the first alert, `[[[event.user_name]]]` is the first follower and `[[[event.bits]]]` the first cheer's bits. A later announcement carries the tags of the event that tipped it over.

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
