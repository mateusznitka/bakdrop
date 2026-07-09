# Installation

Bakdrop can be installed in one of **two ways**. Pick whichever suits you - you do
**not** need both:

- **[Docker](install-docker.md)** - the easiest path. A single image started with
  Docker Compose, with the hourly cleanup job and HTTPS already wired up.
  Recommended if you already have Docker.
- **[Manual (Apache)](install-manual.md)** - install it as a plain PHP app on
  Apache with `mod_php`, for hosts where you would rather not run Docker.

Both give you exactly the same application. Whichever you choose, you finish by
creating the first admin through the web setup page, and then you are ready to
share files.
