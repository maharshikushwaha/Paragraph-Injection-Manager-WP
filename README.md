# Paragraph Injection Manager

**Author:** Maharshi Kushwaha  
**Version:** 1.0  
**License:** GPLv2 or later  

## 📌 Overview

**Paragraph Injection Manager** allows WordPress administrators to inject custom messages into posts every *N paragraphs*.  
Unlike dynamic injectors, this plugin stores the injection HTML in post meta, keeping frontend rendering **fast and lightweight**.  

**Key Features:**

- Injects custom messages with `{category}` placeholder.
- Category links include `title` attribute; no `nofollow` or `noopener`.
- Configurable injection interval (e.g., every 10 paragraphs).
- Batch injection of 100 posts at a time.
- Log system ensures incremental injection without duplication.
- **Clear Records** button removes all injections instantly.
- Post-specific category selection via meta box.
- Frontend is lightweight: no additional queries besides reading post meta.
- Injection HTML persists even if the plugin is deactivated or deleted.

---

## ⚙️ Installation

1. Upload the plugin to `/wp-content/plugins/paragraph-injection-manager`.
2. Activate via the WordPress **Plugins** menu.
3. Navigate to **Paragraph Injection** in the admin menu.

---

## 🛠 Settings

### Custom Injection Message

- Enter the message to inject.
- Use `{category}` to insert a clickable category link.
- Example: You are reading this {category} story on our website.


### Injection Interval

- Determines after how many paragraphs the message will appear.
- Default: `10`
- Minimum: `1`

---

## 🚀 Usage

### Step 1: Save Message & Interval
- Go to **Paragraph Injection → Settings**
- Write your custom message and set the paragraph interval.

### Step 2: Inject into Posts
- Click **Inject Now (Process 100 posts)**
- Processes 100 unprocessed posts at a time.
- HTML is saved to post meta `_pim_injection_html`.

### Step 3: Frontend Rendering
- HTML is inserted after every *N* paragraphs.
- Frontend remains lightweight and fast.

### Step 4: Clear Injections
- Click **Clear Records** to remove all injected messages instantly.
- Uses a single SQL query for fast cleanup.

---

## 🗂 Post-Specific Options

- **Category Selector:** On each post edit screen, choose a specific category for injection.
- Falls back to the first assigned category if none is selected.

---

## 🔄 Workflow Logic

1. Admin saves message & interval → stored in options.
2. Batch injection:
   - Processes 100 posts per click.
   - Skips posts already in log `pim_injected_posts`.
   - Saves injection HTML to `_pim_injection_html`.
3. Frontend:
   - Reads only `_pim_injection_html`.
   - Splits content into paragraphs.
   - Injects HTML after configured interval.
4. Clear Records:
   - Deletes all `_pim_injection_html`.
   - Clears log.

---

## 🔒 Security

- Uses WordPress nonces for all admin actions.
- Only users with `manage_options` capability can manage injections.

---

## 📊 Database Usage

- **Post Meta**
  - `_pim_injection_html` → injected HTML
  - `_pim_selected_category` → selected category
- **Options**
  - `pim_custom_message` → saved message
  - `pim_injection_interval` → interval
  - `pim_injected_posts` → log of processed posts

---

## ❓ FAQ

**Q:** Will this plugin slow down my site?  
**A:** No. Frontend reads only post meta; no extra queries per post.

**Q:** Can I update the message later?  
**A:** Yes. Update in settings and re-run batch injection.

**Q:** Can I inject into custom post types?  
**A:** Currently supports `post` only; can be extended.

---

## 🏁 License

GPLv2 or later — free and open-source.  
