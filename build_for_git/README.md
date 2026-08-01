# build_for_git — APK hosting

Put the release APK in this folder, commit it, and push. GitHub then serves it
over a direct public link you can paste into the admin panel.

This works because **this repository is public**. On a private repo these links
require authentication and players would not be able to download.

## Steps

1. Copy the APK here, named by version so old builds stay distinguishable:

   ```
   build_for_git/realbingo-1.0.0.apk
   ```

2. Commit and push:

   ```bash
   git add build_for_git/ && git commit -m "Add APK v1.0.0" && git push
   ```

3. The public download link is:

   ```
   https://github.com/sachinkorde/bingo-backend/raw/master/build_for_git/realbingo-1.0.0.apk
   ```

4. In the admin panel → **App Releases** → paste that into **Or download link**
   and leave the APK file upload empty. The link takes priority over uploads.

## Size limit

GitHub blocks any single file over **100 MB**, and warns above 50 MB. A typical
release APK (40–60 MB) fits, but a larger build will be rejected on push.

## Trade-off to be aware of

Git keeps every version of every file forever. Each APK committed here adds its
full size to the repository permanently — it cannot be reclaimed by deleting the
file later, only by rewriting history.

That matters here for one specific reason: **Render clones this repository on
every deploy**, so the repo growing by ~50 MB per release makes every future
deploy slower.

`build_for_git/` is listed in `.dockerignore`, so the APKs are at least kept out
of the built Docker image.

## The alternative: GitHub Releases

Same repo, same public links, but release assets are stored **outside** git
history — so the repo never grows, deploys stay fast, and the per-file limit is
2 GB instead of 100 MB.

1. Repo → **Releases** → **Draft a new release**
2. Tag `v1.0.0`, drag in the APK, **Publish release**
3. Right-click the attached APK → **Copy link address**
4. Paste into **Or download link** in the admin panel

Both approaches give a public link the app can download from. Releases is the
better long-term option; this folder is the simpler one.
