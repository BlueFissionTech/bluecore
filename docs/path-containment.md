# Canonical path containment

`BlueFission\Utils\Path` separates lexical separator normalization from
filesystem-backed canonicalization and containment.

`Path::normalize()` only normalizes directory separators. It does not resolve
dot segments, symbolic links, Windows junctions or reparse points, drive or UNC
boundaries, or filesystem case rules. Never use `normalize()` or
`resolveProjectPath()` as a traversal or authorization boundary.

## Canonicalization

Use `Path::canonicalize($path, $basePath = null)` when a caller needs a
filesystem-backed resolution receipt:

```php
use BlueFission\Utils\Path;

$resolution = Path::canonicalize('uploads/report.pdf', $applicationRoot);

if (!$resolution['resolved']) {
    throw new RuntimeException($resolution['reason']);
}
```

The receipt contains `resolved`, `inputPath`, `normalizedPath`,
`canonicalPath`, `exists`, and `reason`. Missing targets can resolve
successfully: BlueCore resolves the nearest existing ancestor, follows links
there, and appends the still-missing tail. An existing non-directory ancestor,
a dangling or unresolvable link, a foreign volume, a wrapper URL, glob syntax,
or a malformed path fails closed.

## Root containment

Use `Path::containment($rootPath, $candidatePath)` before operating on a path
that must stay inside an approved root:

```php
$receipt = Path::containment($applicationRoot . '/uploads', $requestedPath);

if (!$receipt['resolved'] || !$receipt['contained']) {
    throw new RuntimeException($receipt['reason']);
}
```

The receipt contains `resolved`, `contained`, `rootPath`, `candidatePath`,
`candidateExists`, and `reason`. The comparison accepts the canonical root
itself and separator-delimited descendants. It rejects sibling-prefix
collisions, traversal outside the root, absolute paths that resolve outside the
root, parent (`..`) segments, different Windows drives or UNC shares, and
links whose targets leave the root.

On Windows, pending segments containing alternate-stream syntax, reserved DOS
device names, control characters, or trailing dots/spaces are rejected because
Win32 normalization can change their meaning during a later operation.

Reason values include `outside_root`, `different_volume`,
`ancestor_not_directory`, `invalid_path`, `path_escape`,
`resolution_unavailable`, `root_missing`, and `root_not_directory`. Root
resolution failures are prefixed with `root_`.

## Security boundary

Containment is a point-in-time check, not authorization and not an operating
system sandbox. Another process can change a path, link, mount, or reparse
point between this check and a later file operation. Keep the checked root
under trusted control, minimize the time before use, open files with the most
restrictive operating-system semantics available, operate on the returned
canonical path rather than the unchecked input, and revalidate when a workflow
spans mutable filesystem state.
