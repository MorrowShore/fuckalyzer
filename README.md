

# Fuckalyzer 🖕 

### *The WordPress plugin that fucks over Wappalyzer*

<img width="966" height="1435" alt="image" src="https://github.com/user-attachments/assets/508d413a-8fd6-49b0-9d6a-7235f02e8b1c" />


![License](https://img.shields.io/badge/license-AGPL-green)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![Amount of Trolling](https://img.shields.io/badge/Amount%20of%20Trolling-maximum-red)
![Wappalyzer Status](https://img.shields.io/badge/wappalyzer-fucked-purple)

## The Problem

You're security-conscious. You don't want nosy competitors, script kiddies, or that one guy from marketing running Wappalyzer on your site and going *"oh interesting, they're running WordPress 6.4 with Elementor and WooCommerce, let me just check the CVE database real quick..."*

Or maybe you just think it's **none of their fucking business** what your tech stack is.

You may have already tried Wordpress plugins before that aim to hide your stack, but they all do weird things like changing and redirecting your links, which bugs out media and functions

But you know... to not be seen by someone, you don't necessarily need to hide yourself... you could also just **blind them**.

## The Solution

**Fuckalyzer**. It **doesn't hide** your tech stack, it just makes technology detection tools see literally *everything* in existence.

> "Is this site running React or Vue?"  
> "Yes."  
> "...which one?"  
> "Also Angular. And Nuxt. And Next.js. And Drupal. And Laravel. And also ASP.NET."  
> "That's not possible."  
> "And Flutter. At the same time."


## Smart Detection?!
Fuckalyzer only begins working if it detects the user running, **Wappalyzer**, **BuiltWith**, or other technology profilers.

Only *then* does it activate. Your regular visitors see nothing unusual. But snoopers? They get flashbanged.

## Some of the features:
- Zero impact on page load for normal visitors
- Spoofing only activates when profilers are detected
- All fake elements are hidden from actual users
- Headers and cookies blend seamlessly

## How to install:

1. Download `fuckalyzer.php`
2. Drop it in `/wp-content/plugins/`
3. Activate in WordPress admin
4. There is no step 4. You're done. Go grab a coffee.

## Detection Methods:

**1. Resource Probing**
```javascript
// Try to load Wappalyzer's internal files
chrome-extension://gppongmhjkpfnbhagpmjfkannfbllamg/js/inject.js
```
If it loads, gotcha.

**2. DOM Snooping**
Looks for injected `<script>` tags with Wappalyzer signatures.

**3. Message Interception**
Listens for the `postMessage` events these extensions use.

## How We Spoof:

- **Meta tags**: Fake generators for WordPress, Drupal, Elementor, Divi, etc.
- **HTTP Headers**: Fake Vercel, Cloudflare, ASP.NET, Python server headers
- **Cookies**: Fake session cookies for Laravel, Symfony, Java, ASP.NET
- **JavaScript globals**: Fake `window.React`, `window.Vue`, `window.angular`, etc.
- **DOM elements**: Hidden elements that trigger CSS/DOM-based detection
- **CSS variables**: shadcn/ui, Tailwind signatures
- **Link prefetches**: Fake script sources that match regex patterns

## What Gets Detected:

When Fuckalyzer is active, profilers will report your site is running:
```
WordPress, Drupal, React, Vue.js, Angular, Next.js, Nuxt.js, 
Flutter, Dart, jQuery, PHP, Python, Ruby, Ruby on Rails, 
Laravel, Symfony, Express, ASP.NET, Java, Node.js, Firebase, 
Cloudflare, Vercel, Elementor, Divi, Beaver Builder, 
Gravity Forms, WooCommerce, PayPal, Square, Ecwid, OpenCart,
Medium, shadcn/ui, Tailwind CSS, Radix UI, Builder.io,
Otter Blocks, Lovable, EasyDigitalDownloads, and more...
```



## Credits:

Detection fingerprints based on wappalyzer itself at:
**[projectdiscovery/wappalyzergo](https://github.com/projectdiscovery/wappalyzergo/blob/main/fingerprints_data.json)**


---

<p align="center">
  Made with 🖕 for privacy enthusiasts and chaos agents everywhere
</p>

<p align="center">
  <b>Hide your stack. Confuse your enemies. Trust no profiler.</b>
</p>
