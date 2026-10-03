# Automated review evaluation

Tracking issue: [#102](https://github.com/BlueFissionTech/bluecore/issues/102).

Automated findings supplement independent technical review. A bot review does
not authorize a merge, and an approval applies only to the reviewed commit.
Keep the current required checks and branch rules in force throughout a pilot.

## Public service evidence (checked 2026-10-03)

[GitHub's Code Quality documentation](https://docs.github.com/en/code-security/concepts/code-quality/code-quality)
lists rules-based CodeQL coverage for C#, Go, Java, JavaScript, Python, Ruby,
and TypeScript; PHP is absent. Pull requests receive rules-based findings,
while broader AI analysis applies to recently changed code on the default
branch. This does not establish useful PHP PR coverage for BlueCore. Account
entitlement, enablement, billing, and observed output still require verification.

[GitHub's Copilot code review documentation](https://docs.github.com/en/copilot/concepts/agents/code-review)
describes review of any language, organization policy gates, AI-credit usage,
and Actions consumption for agentic context gathering. It also describes an
optional policy allowing bot approvals to satisfy required approvals. BlueCore's
pilot must retain independent human review and must not enable that approval
policy. Public documentation establishes service behavior, not this
organization's plan, permissions, private-code terms, or approved budget.

## Verified repository evidence

The repository's CI workflow runs PHP 8.2 with Composer 2.2 and PHP 8.3 with
the latest Composer on pushes and pull requests. It runs `composer ci` and
the project installer parity check. Its declared runtime is PHP 8.2 or newer.
The application source is primarily PHP. These facts establish the validation
baseline; they do not establish automated review coverage or account entitlement.

The following administrative evidence is still unavailable. This document is
an evaluation checklist, not evidence that either service is enabled:

| Question | Evidence required before pilot | Current state |
| --- | --- | --- |
| Default branch and effective rules | Repository settings plus applicable organization/repository rulesets | `main` is the proposed target; effective rules unverified |
| Code Quality entitlement and enablement | Organization entitlement and repository settings | Unverified |
| Code Quality PHP coverage and triggers | Current supported-language contract, configuration, and observed output on this repository | Unverified; PHP coverage must not be assumed |
| Code Quality Actions/cost impact | Proposed workflow scope, consumption, and account billing evidence | Unverified |
| Copilot plan and seat | Account/organization plan and assigned reviewer entitlement | Unverified |
| Copilot private-code permission | Applicable organization policy and processing/retention terms | Unverified |
| Copilot PHP coverage and trigger | Current language support, branch scope, review-request behavior, and observed output | Unverified |
| Copilot credits and billing | Current account limits and expected per-review consumption | Unverified |

Code Quality checks and Copilot code review are separate services. Code Quality
is not a reviewer that can be requested in the pull request reviewer field.
Record the exact service, setting, and resulting check or review independently.

## Pilot procedure

1. A repository administrator records the current default branch, effective
   protections/rulesets, existing required checks, and allowed merge methods.
2. Verify the current service documentation and account evidence for every row
   above. Record the source URL, date, configured value, and verification owner.
   If evidence cannot be obtained, retain the precise blocker instead of
   inferring entitlement from the appearance of a UI control.
3. Select one small PHP change targeting `main`, with passing baseline checks
   and a known testable behavior. Record its PR URL and exact head SHA.
4. Obtain separate operator approval for any paid seat, new spend, expanded
   Actions use, private-code exposure, or permission change. Document the
   proposed setting and cost/exposure boundary before enabling it.
5. Run only the approved service/trigger on the selected head. Record the actual
   reviewer/check identity, language coverage, finding links, elapsed time,
   consumption, and whether a later push requires another run.
6. Compare findings with independent technical review. Classify actionable,
   incorrect, duplicate, and missed findings; document limitations. Existing
   CI and human review requirements still apply.
7. Review the outcome with the maintainer. Retain, adjust, or disable the pilot
   through an explicitly approved settings change; do not roll it out silently.

## Evidence record

Fill in this record for the chosen pilot; blank values remain blockers:

- Service and documentation sources/date:
- Repository/default branch/effective rules:
- Plan, seat, organization policy, and billing evidence:
- Private-code processing and retention boundary:
- Exact configuration before and after the approved change:
- Operator approval and scope:
- Pilot PR and reviewed head SHA:
- Observed check/review links and language coverage:
- Resource consumption and unexpected effects:
- Independent review comparison:
- Maintainer decision and any rollback:

Completion of this document alone does not close #102. Closure requires the
verified administrative evidence and observed pilot result specified in the
issue. No repository settings, dependencies, or application behavior change
is part of this proposal.
