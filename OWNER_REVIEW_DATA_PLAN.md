# Natural owner review data plan

This is a small fictional tutoring business for manual product review. Leave the dataset in place after verification. Its exact IDs and ownership belong in `OWNER_REVIEW_DATASET_MANIFEST.md`; portal verification details stay in protected private storage outside Git and the public web root.

The deployed application remains `094b2c45375d5b221d9a6a132af670b580dd4d05`. No public copy replacement, application deployment, architecture review, UI redesign, live Bunny configuration, or final-launch reset is part of this operation.

| Persona | Natural review state |
|---|---|
| OR-01 Sarah Black | Active one-hour purchase, completed tutoring, upcoming lesson, shared plan/homework/Resource, Foundations in progress |
| OR-02 Iris Smith | New onboarding questionnaire, settled starter purchase, future booking, newly assigned Foundations |
| OR-03 Daniel Foster | Discounted purchase, partial manual settlement, remaining receivable and two installment forecasts, normal booking history |
| OR-04 Maya Collins | Several completed lessons, advanced course progress, graded quiz, submitted work and feedback, one completed short course, private pronunciation practice |
| OR-05 Omar Hassan | Separate one-hour/two-hour allocations, a real reschedule preserving the original debit, upcoming tutoring |
| OR-06 Sophie Martin | One remaining session/near expiry, follow-up homework and Resource, incomplete Foundations, a small follow-up task |

Use six fictional profiles with reserved example-domain addresses and reserved fictional telephone numbers. No third-party contact information, live payment-provider transaction, or outbound message is needed. Existing Cash payment method and original tutoring definitions are reused. Original launch content, forms, resources, owner credentials, availability, entitlement definitions and protection profiles remain unchanged.

Past tutoring activity will be simulated in bounded console processes at documented historical times, using the same identity, availability, signed-slot, booking, typed-ledger and completion services. The process restores its clock in `finally`; the production clock/configuration is unchanged. Each purchase precedes its lessons, each completion occurs after its lesson ends, and current/future bookings obey current availability and notice. Dates and debit rows are not patched with SQL.

Create three compact courses: **Beginner Egyptian Arabic — Foundations**, **Everyday Egyptian Arabic Conversation**, and private **Maya's Pronunciation Practice**. Use real rich text, the existing Arabic Alphabet Resource, a new passive **Greetings and Introductions** PDF handout, an ordered quiz and a text/file assignment. The current upload safeguard rejects the legacy Resource PDF for a new tutoring attachment; preserve it as-is and use the newly rendered/verified handout for uploads. Access comes from the ordinary tutor assignment service. Completion, quiz scores and assignment status must come from the ordinary learning/assessment services; no direct progress writes or pretend video assets.

Teaching records include small shared/private notes, plans, milestones, homework, staff preparation, resources and feedback. Use published existing onboarding questions where suitable. Add only a few useful staff notes/tasks. Synchronize Student notifications through their current authority; review names/titles in the actual owner and Student interfaces.

The two existing Telegram countdown rules (#1/#2) are paused while fictional upcoming bookings exist. Their definitions, recipients and authentication remain protected. Record this reversible review safeguard and its original enabled state in the manifest. Restore the original state only after removing the fictional booking/reminder graph at final launch. Mail remains the established `log` transport.

Journal every created primary/reference ID, parent, file/hash and indirect notification/audit/analytics fact. Capture all-table snapshots between phases to include secondary records. An existing Student reset does not include LMS tables and must fail closed until their exact reviewed graph is removed; future cleanup must re-query rather than assume a complete Student reset alone is sufficient.

Verify live FK integrity, typed debit/grant provenance, finance reconciliation, UTC/timezone snapshots, file hashes, access/progress/assessment/notification ownership, unchanged owner/security and production source, disabled destructive tools and retained recovery. Keep previous tests/reports/evidence intact. Define final-launch ground zero separately and do not execute it now.

## Verified realization

Creation completed and remains intact on 9 October 2026: six Students, thirteen bookings, seven manual purchases/payments and three published courses. Sarah reached 50% through a normal HTTPS quiz; Maya is 75% in Foundations and 100% in Conversation, with private work Needs Revision; Sophie is 25%; Iris remains Not Started. Iris used the actually assigned published Learning Profile form #2; the original pre-booking-only form #1 and its triggers stayed unchanged. Canonical notification history retains 42 Student items with 20 unread and 13 admin booking notices with six unread. Bounded actual HTTPS review generated three authenticated Visitors and 21 facts; console activity was not fabricated as analytics traffic. See the final manifest/report for exact primary and private-key ownership.
