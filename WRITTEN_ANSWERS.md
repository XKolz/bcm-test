# Written answers — Q2, Q3, Q7, Q8, Q10

(Q1, Q4, Q5, Q6 and Q9 are in their folders, each with an `ANSWER.md`.)

---

## Q2 — An editor cannot upload images, but a colleague can

The difference is the person, the file, or the device/network. I would narrow it down by comparing the two, cheapest checks first.

**Likely causes**

1. **The file itself:** too large for `upload_max_filesize` / `post_max_size` or the WordPress limit, or a type that is not allowed (e.g. an iPhone HEIC photo, or WebP/SVG on an older setup). The colleague may simply be uploading smaller JPEGs.
2. **Role or capability:** the editor's account is missing `upload_files`. For example, it was given a different role by mistake, a role-editor plugin changed the Editor role, or, on multisite, they have a different role on this site.
3. **Browser or network:** a stale admin session or cookie, a browser extension, or a security layer (Wordfence, Cloudflare WAF, hosting firewall) blocking `async-upload.php` from the editor's IP or flagging their requests.
4. Less likely, since the colleague can upload: a server-wide problem such as `wp-content/uploads` permissions or a full disk. That would usually affect everyone.

**Order and evidence**

1. **Get the exact error and the file.** Ask for a screenshot of the message and the file. Try the same file from my account and the editor's file from the colleague's account. If the file fails for everyone, it is the file: check its size and type against the limits in Tools → Site Health → Info → Media / Server.
2. **Check the account.** Users → the editor's role; `wp user get <id>` and `wp cap list editor` (WP-CLI) to confirm `upload_files`. A missing "Add Media" button or "You don't have permission to upload files" points here.
3. **Check the device and network.** Ask them to try a private window and another browser, then another network (phone hotspot). In the browser's Network tab, a 403 or an HTML block page from `async-upload.php` points to the firewall; then check the security plugin or WAF logs for their IP and that timestamp.
4. **Server logs** (PHP error log, web server log) at the time of the failed upload, if the above are inconclusive.

**Once the cause is confirmed**

- Fix the root cause, not just this user: restore the capability in code or the role plugin (and find out what changed it), raise the limit or explain the allowed formats (or enable HEIC conversion), or adjust the WAF rule that produced a false positive.
- Have the editor upload again while I watch, so we know it works for them, not just for me.
- Check whether other users are affected, and note the cause and fix in our support notes so the next person finds it faster.

---

## Q3 — Article page slow, especially on mobile

**Measure first, for all three:** field data from real readers (Core Web Vitals in Search Console / CrUX, or our own RUM), split by mobile and page type, because that shows what readers actually experience. Then lab tests (Lighthouse / WebPageTest) on a mid-range Android, 4G throttling, test location near our readers (e.g. Lagos), to find the cause. Query Monitor for server time.

**1. Images.** Hero and inline images are often far larger than the phone needs.
- Measure first: which element is the LCP, its size in KB and its load time; total image bytes per article.
- Change: correct `srcset`/`sizes`, WebP/AVIF, explicit width/height, `fetchpriority="high"` on the hero (never lazy-load it), lazy-load images below the fold.

**2. Third-party scripts (ads, analytics, embeds).** On news sites these usually dominate main-thread time on mobile.
- Measure first: main-thread time and request count per third party (WebPageTest/Lighthouse "third-party usage"), Total Blocking Time and INP, layout shift caused by ad slots (CLS).
- Change: load scripts `async`/`defer`, lazy-load below-the-fold ad slots and social embeds, reserve space for ad slots, remove tags nobody uses.

**3. Server response / caching.** Slow first byte delays everything.
- Measure first: TTFB p75 for anonymous readers, cache hit ratio at the CDN/page cache, slow queries in Query Monitor.
- Change: full-page cache at the CDN edge for anonymous readers with purge on publish/update, plus a persistent object cache (Redis) for the back end.

**Chosen: edge page caching (3).** How I would check that it helped without breaking things:

- **Before:** record a baseline for a week: TTFB and LCP p75 (field), ad impressions and fill rate per page view (ad server), page views and events per session (analytics).
- **Roll out gradually:** one section or a percentage of traffic first, so the rest is a control group.
- **Signed-in behaviour:** the cache must bypass anyone with a `wordpress_logged_in_*` (or subscription/paywall) cookie, plus admin, preview, cart and comment-submission URLs. Test as each user type: admin bar appears, previews show drafts, logged-in readers see their own state and never another user's, and the response header shows `BYPASS` for them.
- **Ads:** ad scripts run in the browser, so cached HTML is fine, but check that slots still render, targeting values that were printed into the HTML (section, tags, article ID) are still correct per page, and impressions per page view do not drop compared with the control group.
- **Analytics:** page views per day match the control group trend; tag debugger shows one page view per load, not zero or two.
- **Content freshness:** edit and update a test article and confirm the change is visible from several locations within the expected purge time. (This is exactly the Q7 failure mode.)
- **Success:** TTFB and LCP p75 improve in field data over 1–2 weeks, with ads and analytics within normal variation.

---

## Q7 — Some readers see an old version of a corrected article after a release

**Assumptions:** the site sits behind a CDN and/or page cache, and possibly more than one application server. I have not seen the logs, so the cause is unknown.

**First three checks**

1. **Compare what the cache serves with what the origin serves.** `curl -I` the article URL several times and from a couple of regions/devices; note `Age`, `Cache-Control`, `CF-Cache-Status` / `X-Cache` and the content (e.g. grep for the corrected sentence). Then request the origin directly, bypassing the cache (cache-busting query string or origin hostname), and as a logged-in user.
2. **Check the content in WordPress.** Is the corrected version actually published, not saved as a draft, pending, or scheduled? Check revision history and timestamps. Is there a second URL (changed slug, AMP page, duplicate post) that readers might be reaching?
3. **Check what the release changed and when.** Compare the time of the correction, the release, and any cache purges. Diff the release for anything touching caching: headers, purge-on-update hooks, a service worker, asset versioning, transients/object cache used by the template. Confirm every app server is running the same version (a partial deploy behind a load balancer can produce "some readers" behaviour).

**Caching versus code/content**

- **Caching problem:** the origin always serves the corrected article; old copies come only from the cache (high `Age`, `HIT`, specific edge locations, or a stale service worker on certain browsers). Purging the URL fixes it.
- **Code or content problem:** the origin itself serves the old text, or alternates between old and new (different servers/versions, a stale transient, the template reading the wrong revision), or the corrected text never reached the published post. Purging does not fix it, or it comes back.

**Rollback**

- I would **not** ship another release on top of an unknown fault. A new change makes it harder to tell what is broken.
- **Roll back** if the evidence points at the release (for example, the purge hook stopped firing, or new code renders stale content) or if the cause is still unclear and readers are seeing an uncorrected article. Corrections carry legal and trust risk. Roll back only if it is safe: no database migrations that the old version cannot handle.
- **Do not roll back** for a pure cache issue. Purge the affected URLs, then fix the purge so it cannot recur.

**Verifying recovery**

- Purge, then request the article from several regions, devices and browsers (including mobile data); the response should show the corrected content with a low `Age` and then cache normally.
- Compare cached and origin copies; edit a test article to confirm that updates now invalidate the cache.
- Check other articles corrected since the release, monitor for an hour, and confirm with the editor who reported it.

**Update to the editor (two sentences)**

> Some readers are still seeing the uncorrected version of the article; we are checking now whether a cache or the release itself is responsible, and we will update you by [time], rolling the release back if it is at fault. Please hold the next release until then, so we do not add another change on top of a problem we have not yet pinned down.

---

## Q8 — Editors copying titles, links and images into several tools

**What I would ask or observe first**

- Sit with one or two editors while they publish real articles. Which tools (social scheduler, newsletter, WhatsApp/Telegram channels, Slack, partner sites)? What exactly is copied (headline, social headline, URL, image, excerpt)? How long does it take per article?
- What goes wrong and how often: preview or staging links pasted instead of the live URL, missing UTM tags, wrong or badly cropped image, headline later corrected but old one shared?
- Does each tool build its own preview from a pasted URL (Open Graph / Twitter Card tags), or does it need the title and image separately?

**Simpler alternative, little or no new code**

If most tools already build their preview from the URL, the editor should only need to paste the final link. Make sure the Open Graph and Twitter tags (title, description, image size) are always correct, which SEO plugins such as Yoast already support, and agree a short checklist: "publish, copy the live link from the post screen, paste." Mistakes then come only from the URL, and the preview always matches the article.

**Smallest useful improvement (one engineering day)**

A **"Share kit" box** in the post editor, shown once the post is published:
- the live canonical URL, with one copy button per channel that adds that channel's UTM tags;
- the headline and social headline, each with a copy button;
- the featured image, with download links in the sizes each channel needs.

It only reads data that already exists in WordPress, so it is small (a single plugin/metabox), low-risk, and easy to remove. It removes the error-prone steps (finding the right URL, adding UTMs, finding the right image) without automating posting.

**My choice:** start with the no-code step (fix the sharing tags, use the link only) because it may solve most of the problem in an hour. Build the Share kit only for the tools that cannot read a URL, or for UTMs and image sizes if those turn out to be the main source of errors.

**What stays under editorial control:** the wording on each channel, which image is used, whether and when to post. Nothing is published or posted automatically.

**Testing with one editor:** time three or four of their real articles before, then the same with the change, sitting with them. Note every place they hesitate or go back to the article. Ask what they would change. Adjust, then give it a week of their normal work.

**Judging success:** time spent per article on this task drops noticeably; no wrong or preview links shared over the trial; UTM-tagged traffic appears correctly in analytics; and the editor keeps using it without being reminded (and other editors ask for it).

**A similar improvement I made (≤100 words)**

> ⚠️ **Samuel: this is a draft based on your weekly activity-report work. Replace the bracketed parts with your real details, or swap in a different example, before submitting.**
>
> At Techxon I produced weekly activity reports for several client projects by copying task notes into Excel by hand, which took about [X hours] each week and sometimes mixed up dates between projects. I wrote a script that generates one formatted workbook per project from a single structured log, with a consistent layout for each client. Preparation time dropped to [Y minutes], and the date and project mix-ups stopped. I learned to first watch exactly where the mistakes happened: automating the error-prone step mattered more than automating everything.

---

## Q10 — Endpoint that updates article status, "safe because only editors see the button"

**Review of the claim:** it is not safe. Hiding a button is a UI decision, not a security control. The endpoint is a public URL: anyone who is logged in (a subscriber, a contributor) can call it with `curl` or the browser's developer tools, and the ID and status in the request are fully under their control. Without a nonce, another website can also make an editor's browser call it for them (CSRF). Every check must happen on the server, on every request.

**Server-side checks required**

1. Correct HTTP method only (POST), so the change cannot be triggered by a link or image tag.
2. The user is authenticated.
3. A valid nonce (CSRF protection) for the logged-in user.
4. Input validation: the ID is a positive integer for an existing post of the expected type; the status is in an explicit allow-list (e.g. `draft`, `pending`, `publish`), never passed through as free text.
5. Authorization **for this post and this change**, using capabilities rather than role names: `current_user_can('edit_post', $id)`, plus `publish_post` (or `publish_posts`) to publish, `delete_post` to trash.
6. Business rules: allowed transitions, post not locked by another editor.
7. Update through WordPress APIs (`wp_update_post` with error handling), not raw SQL; return a minimal response; write an audit log entry (who, post, old → new status).

**Order (pseudocode, WordPress REST API)**

```php
register_rest_route( 'bcm/v1', '/articles/(?P<id>\d+)/status', [
  'methods'  => 'POST',                                  // 1. method
  'args'     => [                                        // 4. input validation
    'id'     => [ 'validate_callback' => fn($v) => absint($v) > 0, 'sanitize_callback' => 'absint' ],
    'status' => [ 'enum' => [ 'draft', 'pending', 'publish' ], 'required' => true ],
  ],
  'permission_callback' => function ( $req ) {
    if ( ! is_user_logged_in() ) return false;           // 2. authenticated (REST also checks
                                                          // 3. the X-WP-Nonce for cookie auth)
    $post = get_post( $req['id'] );
    if ( ! $post || $post->post_type !== 'post' ) return new WP_Error( 'not_found', '', [ 'status' => 404 ] );
    if ( ! current_user_can( 'edit_post', $post->ID ) ) return false;                 // 5. this post
    if ( $req['status'] === 'publish' && ! current_user_can( 'publish_post', $post->ID ) ) return false;
    return true;
  },
  'callback' => function ( $req ) {
    if ( wp_check_post_lock( $req['id'] ) ) return new WP_Error( 'locked', '', [ 'status' => 409 ] ); // 6.
    $old    = get_post_status( $req['id'] );
    $result = wp_update_post( [ 'ID' => $req['id'], 'post_status' => $req['status'] ], true );         // 7.
    if ( is_wp_error( $result ) ) return $result;
    audit_log( get_current_user_id(), $req['id'], $old, $req['status'] );
    return [ 'id' => $req['id'], 'status' => $req['status'] ];
  },
] );
```

With cookie authentication, the WordPress REST API rejects requests without a valid `X-WP-Nonce` (it treats them as logged out), which covers step 3. A custom `admin-ajax` handler would need `check_ajax_referer()` explicitly.

**Two tests that must be rejected**

1. **Wrong user:** logged in as a Subscriber (or a Contributor, who can edit their own drafts but not publish), with a valid nonce, POST `{ id: <an editor's draft>, status: "publish" }` → **403**, and the post status is unchanged in the database.
2. **CSRF / missing nonce:** logged in as an Editor, send the same request without the nonce (or with an expired one, or from another origin) → **401/403**, post unchanged.

(Also worth adding: an invalid status such as `trash` or `<script>` → 400; a non-existent ID → 404.)

**Raising it with the developer**

In the review, privately and specifically, not as a judgement: "The button check is good UX, but the endpoint itself needs to enforce it, because anyone logged in can call it directly. Here's a `curl` that publishes a draft as a subscriber on staging." Show the reproduction, point to the WordPress docs on `permission_callback` and capabilities, and offer to pair on the fix and the tests. Mark the review as **changes requested**, because this is a release blocker.

**Must be fixed before release:** a real `permission_callback` with per-post capability checks (including publish rights), nonce/CSRF protection, the status allow-list and ID validation, and automated tests for the rejected cases above. Audit logging is strongly recommended; it can follow shortly after if needed.
