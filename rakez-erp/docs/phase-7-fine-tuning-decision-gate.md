## Phase 7 Fine-Tuning Decision Gate

Fine-tuning is blocked by default.

## Fine-tuning is not justified if the problem is actually in

- prompt design
- tool selection or gating
- retrieval quality
- schema design
- policy normalization
- ambiguous business rules
- weak evaluation discipline

## Proof required before any tuning work starts

- a stable failure set remains after Phases 0 through 6
- those failures are reproducible on representative prompts
- those failures cannot be fixed acceptably with:
  - prompt changes
  - tool-routing changes
  - stricter schemas
  - retrieval improvements
  - policy normalization
- an offline eval set exists and is versioned
- rollback criteria and success criteria are written down

## Recommended evidence package

- failure taxonomy with counts
- before/after prompt-only attempts
- representative Arabic and English examples
- tool-on vs tool-off comparison where relevant
- cost estimate for training + serving
- expected operational owner

## Mandatory no-go criteria

- \"we want better Arabic\" without measured failure classes
- \"the model hallucinates\" without proving tool/prompt/retrieval fixes failed
- lack of benchmark/eval corpus
- lack of rollback strategy

## Go criteria

- narrow, repeated, high-value failure class
- stable eval set
- measurable baseline
- measurable improvement target
- clear serving cost acceptance
