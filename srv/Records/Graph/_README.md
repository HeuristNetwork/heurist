# Record graph

## Overview

Builds renderer-neutral graph documents for the `heurist-graph` client. A graph
document contains the seed result-set records, internal edges discovered for the
requested links, the link and path namespaces, and the effective node/edge/depth
budget. The payload is self-contained: the client renders it as a full initial
graph or merges it as an incremental fragment.

Initial links return only edges whose two endpoints are both in the seed result
set. They never add external records. `links: "all"` returns every internal
edge, bounded by the effective edge budget. Interactive single-rule expansion is
added in a later stage.

## Key files

- `GraphService.php` — seed query, edge discovery, header loading, limit report.
- `GraphLinkParser.php` — compact `source:operator:target` link-spec parsing.
- `GraphEdgeDiscovery.php` — set-bounded `recLinks` traversal.
- `GraphRequest.php`, `GraphResult.php` — graph contracts.

## Limits

Server ceilings in `GraphRequest` are the final authority. A client may request a
smaller `maxNodes`, `maxEdges` or `maxDepth`; it can never raise them. Every
response reports `nodesReturned`, `edgesReturned` and `truncated`.
