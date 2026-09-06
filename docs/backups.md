# Backups

Two archives, one machine, and a copy that leaves it.

- **Where they are written:** `storage/app/private/backups`, outside `public/` — an archive holds the whole catalogue, every order and every customer's address.
- **Two kinds:** a *full* archive (database, uploaded images, private files) taken by hand from the back office, and a *database* archive taken on a schedule. The back office labels each row.

---

## The scheduled archive

`php artisan backup:database` writes the database alone and prunes its own trail. The images and private files are what make a full archive heavy, and they change rarely: leaving them out is what makes a five-minute rhythm affordable — about 860 KB an archive against tens of megabytes.

| | |
|---|---|
| Cadence | Every 5 minutes, declared in `routes/console.php` |
| Retention | The 288 most recent, a full day of history (`BACKUP_DATABASE_KEEP`) |
| Naming | `armooutdoor-db-YYYY-mm-dd-His.zip`, distinct from the hand-made `armooutdoor-YYYY-mm-dd-His.zip` |
| Pruning | Only ever the `db-` archives: nothing automatic throws away what somebody deliberately made |

An archive that catches nothing throws rather than reporting success, because `ZipArchive` writes no file at all in that case and a schedule nobody watches would say « fine » forever. Failures are logged at error level.

### The cron entry the server needs

Nothing fires until the scheduler is registered. Either give Laravel its minute, which is where future jobs will live too:

```
* * * * * cd /var/www/armooutdoor.fr && php artisan schedule:run >> /dev/null 2>&1
```

or call the command directly if you would rather not run a minute-cron for one job:

```
*/5 * * * * cd /var/www/armooutdoor.fr && php artisan backup:database >> /dev/null 2>&1
```

`deploy.sh` runs `config:cache`, so a change to `BACKUP_DATABASE_KEEP` only takes effect after a deploy.

---

## Sending them off the machine

An archive on the same disk as the site survives a mistake, not a disk failure. The copy goes to Google Drive through **rclone**, wrapped in a **crypt** remote so Google stores bytes it cannot read: the archives hold personal data, and encrypting before upload answers most of what a transfer outside the EU would otherwise raise.

### One-time setup

**1. Install rclone on the server.**

```
curl https://rclone.org/install.sh | sudo bash
```

**2. Create the Drive remote** with `rclone config`: a new remote named `gdrive`, storage `drive`, `client_id` and `client_secret` left **blank** — rclone's own credentials avoid the seven-day refresh-token expiry that a Google Cloud project still in « Testing » would hit. Choose the **`drive.file`** scope, so rclone only ever sees files it created itself. Leave `root_folder_id` and `service_account_file` blank and skip the advanced config.

At « Use web browser to automatically authenticate? » answer **no**: the server has none. rclone prints a command; run it on a machine that has a browser, sign in, and paste the token back. Answer no to the Shared Drive question.

**3. Wrap it in a crypt remote**, again with `rclone config`: a new remote named `gdrive-crypt`, storage `crypt`, remote `gdrive:armooutdoor-backups`, `standard` filename encryption, `true` for directory names, and let rclone generate both the password and the salt.

> **The crypt password lives off the server.** Put it in a password manager, never only in `rclone.conf`. A server that dies taking the password with it leaves the Drive copies unreadable, which defeats the whole point of an offsite backup.

Then lock the config down: `chmod 600 ~/.config/rclone/rclone.conf`.

**4. Prove it once by hand.**

```
rclone copy /var/www/armooutdoor.fr/storage/app/private/backups gdrive-crypt: --progress
rclone ls gdrive-crypt: && rclone ls gdrive:armooutdoor-backups
```

The first listing shows your filenames; the second shows gibberish. That is the crypt layer doing its job.

### The two cron entries

The upload runs on its own five-minute rhythm, offset by two minutes from the archive job and skipping anything younger than a minute, so it never grabs a zip mid-write:

```
2-59/5 * * * * /usr/bin/rclone copy /var/www/armooutdoor.fr/storage/app/private/backups gdrive-crypt: --config /root/.config/rclone/rclone.conf --min-age 1m --log-file /var/log/rclone-backup.log --log-level INFO
```

The local side keeps a day; Drive would otherwise keep everything forever:

```
0 4 * * * /usr/bin/rclone delete gdrive-crypt: --min-age 30d --config /root/.config/rclone/rclone.conf
```

Use the crontab of whichever user owns `rclone.conf`, and keep the explicit `--config`: cron often runs without a `HOME`, and rclone would report « remote not found » instead.

At about 240 MB a day, thirty days is roughly **7.3 GB** — it fits a free 15 GB Drive, but that quota is shared with Gmail and Photos, so watch it. Shortening the Drive retention to a fortnight halves it.

---

## Restoring

You need rclone and the crypt password, nothing else.

```
rclone copy gdrive-crypt:armooutdoor-db-2026-09-06-190856.zip /tmp
unzip /tmp/armooutdoor-db-2026-09-06-190856.zip -d /tmp/restore
```

The database sits at `database/database.sqlite` inside the archive. Put the site down, replace the file, put it back up.
