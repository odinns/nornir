# AI And MCP Architecture

## Purpose

Define the AI and MCP shape without inventing a parallel application inside the application.

## Preferred approach

Use Laravel-native AI and MCP features and packages first.

Rules:

- prefer supported Laravel integrations over custom orchestration
- keep AI and MCP behavior in application services, actions, and jobs
- keep prompts and skills versioned as explicit assets

## AI generators

Generators are application components that:

- select evidence from MySQL
- load a domain-specific prompt or skill
- call the configured model through Laravel-integrated tooling
- validate structured output
- write markdown to `wiki/`
- record run metadata and provenance in MySQL and `data/`

## Generator classes of work

- source page compilation
- Muninn extraction and structuring
- Huginn synthesis
- judgment extraction for decisions, observations, contradictions, and promoted outputs
- output-page generation for preserved analyses

## Prompt and skill contract

Every generator prompt or skill must define:

- intended domain
- allowed inference level
- required evidence shape
- forbidden behaviors
- expected output schema
- provenance expectations
- version identifier

## MCP stance

MCP belongs in Heimdallr and related application services, not in random scripts.

Rules:

- bounded access only
- explicit allow rules
- read-only in v1
- no uncontrolled traversal
- fetched material must pass through intake and import boundaries before it becomes canonical

## Failure handling

AI output is rejected when:

- required evidence is missing
- output shape is invalid
- provenance links are incomplete
- the generator makes unsupported claims
- the generator tries to accept a decision, apply a correction, or persist a high-level observation without the required judgment contract

## Review checklist

- Laravel-native integration is used unless a real gap exists
- prompts are versioned assets
- model output is validated before persistence
- provenance is recorded

## Acceptance checks

- AI generation can be swapped or upgraded without rewriting domain policy
- MCP access stays bounded and auditable
