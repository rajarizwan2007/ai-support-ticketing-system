# Introduction

Create, list, show and reply to support tickets in your organization.

<aside>
    <strong>Base URL</strong>: <code>http://acme.localhost:8088</code>
</aside>

    Each organization has its own subdomain, and API requests go to it: `http://acme.localhost:8088/api/v1/...`.
    A token only works on the subdomain of the organization it was created in.

    Requests are limited to 60 per minute per user (or per IP before login). Over the limit you get `429 Too Many Requests`.

