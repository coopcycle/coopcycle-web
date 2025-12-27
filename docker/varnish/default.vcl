vcl 4.1;

backend default {
    .host = "nginx";
    .port = "8080";
}

acl purge {
    "127.0.0.1";
    "nginx";
    "php";
    "varnish";
}

sub vcl_recv {
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(403, "Forbidden"));
        }
        if (req.http.Cache-Tags) {
            ban("obj.http.Cache-Tags ~ " + req.http.Cache-Tags);
            return (synth(200, "Banned"));
        }
        return (synth(400, "Missing Cache-Tags header"));
    }

    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(403, "Forbidden"));
        }
        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Avoid caching Centrifugo & WebSocket
    if (req.url ~ "^/centrifugo") {
        return (pass);
    }

    return (hash);
}

sub vcl_hash {
    hash_data(req.http.host);
    hash_data(req.url);
}

sub vcl_backend_response {
    if (beresp.http.Cache-Control ~ "private") {
        set beresp.uncacheable = true;
    } else {
        set beresp.ttl = 1h;
    }

    if (beresp.http.Cache-Tags) {
        set beresp.http.X-Cache-Tags = beresp.http.Cache-Tags;
    }
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
