Written against the unreleased `main` branch, after v1.3.3.

The plugin can send an event to a Google Analytics 4 property for every request to `llms.txt` or `llms-full.txt` on any subsite, so you can see which agents fetch them. One configuration covers the whole network. `robots.txt` is not tracked: a physical `robots.txt` is served by the web server without WordPress, so the plugin cannot see those requests. Use the server's access logs for them.

**Note:** each event carries the requester's IP address and user agent. Check that sending them to GA4 fits your organization's privacy policy before you turn tracking on.

1. **In GA4, find the Measurement ID.** Go to Admin, then Data Streams, and select the web stream. The ID has the form `G-XXXXXXXXXX`.
2. **In the same stream, create a Measurement Protocol API secret.** Under Measurement Protocol API secrets, select Create, and copy the secret's *value*, not its name.
3. **In WordPress, go to Network Admin, then Settings, then Site Files Tracking.** Enter the **GA4 Measurement ID** and the **Measurement Protocol API Secret**, then select **Save Tracking Settings**. A **Tracking settings saved.** notice appears.
4. **Confirm it is on:** the Site Files page on any subsite now reads **GA4 tracking: Enabled**.
5. **Request `/llms.txt` on a subsite**, then open GA4's Realtime report. A `wayfinding_request` event appears within a short time.
6. **To report on the event's parameters in Explorations**, register `file_name`, `user_agent`, `requester_ip` and `site_host` as custom dimensions under Admin, then Custom definitions. If a name is not offered in the list yet, type it: GA4 only lists parameters it has already received. Realtime shows the parameters without this step.

To turn tracking off, empty both fields and save. The plugin sends nothing unless both are set.

## Reference

- [Served files](Served-files.md#the-tracking-event): every field of the event and how it is sent.
- [Admin pages and settings](Admin-pages-and-settings.md#site-files-tracking): where the two values are stored.
- [Troubleshooting](Troubleshooting.md#no-wayfinding_request-events-in-ga4): no events arriving.
