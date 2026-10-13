# Security policy

Report a vulnerability privately through [GitHub Security Advisories](https://github.com/PDFToolkit/pdf-fonts/security/advisories/new).
Include the affected commit, a minimal input, configured limits and observed result.

The converters accept bytes supplied by the application and never open a path or URL.
`FontProgramLimits` bounds the input bytes before parsing. Binary reads are bounded
to their table; table ranges, glyph references, composite cycles and recursion
depth are checked. Unicode input and character mappings have explicit limits.
Invalid and unsupported inputs are rejected explicitly; the documented tests verify these controls.
Configure limits for the workload and apply memory and execution-time quotas at the application boundary.
Passing this suite does not establish the absence of every possible vulnerability.

## Supported versions

The `0.1.x` series receives security fixes. PHP 8.3 or newer on a 64-bit platform
is required; CI checks PHP 8.3, 8.4 and 8.5. Use a maintained PHP version with its
security updates.

The shared PDF model has its own [security policy](https://github.com/PDFToolkit/pdf/blob/master/SECURITY.md).
