# Branch Protection & Required Checks (TASK-G04)

## Status: BLOCKED (wymaga uprawnien administratora repozytorium)

Konfiguracja branch protection dla galezi `main` wymaga uprawnien na poziomie GitHub Organization Admin lub Repository Admin. Ponizszy dokument zawiera kompletna specyfikacje do recznie zastosowania przez uprawniona osobe.

---

## Wymagana konfiguracja branch protection dla `main`

### Przez GitHub UI

Sciezka: **Settings → Branches → Add branch protection rule**

| Opcja | Wartosc | Uzasadnienie |
|-------|---------|--------------|
| Branch name pattern | `main` | Chroni galaz produkcyjna |
| Require a pull request before merging | ✅ | Nie mozna pushowac bezposrednio |
| Required approving reviews | `1` | Min. 1 review przed merge |
| Dismiss stale pull request approvals | ✅ | Po nowym commicie wymaga ponownego review |
| Require status checks to pass | ✅ | Required checks musza byc zielone |
| Require branches to be up to date | ✅ | Branch musi byc aktualny przed merge |
| Require conversation resolution | ✅ | Wszystkie komentarze resolved |
| Restrict force pushes | ✅ | Zakaz force push na main |
| Restrict deletions | ✅ | Nie mozna usunac galezi main |

### Wymagane Status Checks

Nastepujace checks musza byc zielone przed merge do `main`:

```
nightly-quality / nightly-quality
```

Jesli dodane zostana osobne PR checks, nalezy dodac:
```
smoke-test
unit-tests
integration-tests
```

### Przez GitHub CLI (po uzyskaniu uprawnien)

```bash
# Zainstaluj gh CLI jesli brak:
# brew install gh  /  apt install gh

# Ustaw branch protection:
gh api repos/{owner}/{repo}/branches/main/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"contexts":["nightly-quality / nightly-quality"]}' \
  --field enforce_admins=false \
  --field required_pull_request_reviews='{"dismiss_stale_reviews":true,"required_approving_review_count":1}' \
  --field restrictions=null \
  --field allow_force_pushes=false \
  --field allow_deletions=false \
  --field required_conversation_resolution=true
```

### Przez GitHub Actions (Rulesets API – wymaga GitHub Enterprise lub token z admin:repo)

```yaml
# .github/workflows/setup-branch-protection.yml
# UWAGA: Uruchamiac tylko z tokenem o uprawnieniach admin:repo
name: Setup Branch Protection
on: workflow_dispatch
jobs:
  protect:
    runs-on: ubuntu-latest
    steps:
      - name: Apply branch protection ruleset
        uses: actions/github-script@v7
        with:
          github-token: ${{ secrets.ADMIN_TOKEN }}
          script: |
            await github.rest.repos.updateBranchProtection({
              owner: context.repo.owner,
              repo: context.repo.repo,
              branch: 'main',
              required_status_checks: {
                strict: true,
                contexts: ['nightly-quality / nightly-quality']
              },
              enforce_admins: false,
              required_pull_request_reviews: {
                dismiss_stale_reviews: true,
                required_approving_review_count: 1
              },
              restrictions: null,
              allow_force_pushes: false,
              allow_deletions: false,
              required_conversation_resolution: true
            });
            console.log('Branch protection applied to main');
```

---

## Plik konfiguracyjny (do zastosowania przez admina)

Ponizszy plik JSON opisuje kompletna konfiguracje do zastosowania przez API lub UI:

```json
{
  "branch": "main",
  "protection": {
    "required_status_checks": {
      "strict": true,
      "contexts": [
        "nightly-quality / nightly-quality"
      ]
    },
    "required_pull_request_reviews": {
      "dismiss_stale_reviews": true,
      "required_approving_review_count": 1,
      "require_code_owner_reviews": false
    },
    "enforce_admins": false,
    "restrictions": null,
    "allow_force_pushes": false,
    "allow_deletions": false,
    "required_conversation_resolution": true,
    "required_linear_history": false,
    "allow_fork_syncing": true
  }
}
```

---

## Weryfikacja po zastosowaniu

```bash
# Sprawdz aktualna konfiguracje:
gh api repos/{owner}/{repo}/branches/main/protection | jq .

# Przetestuj ze bezposredni push jest odrzucany:
git push origin main  # Powinno zwrocic blad Protected branch

# Przetestuj ze PR bez reviewu nie moze byc zmergowany:
gh pr merge <PR_NUMBER>  # Powinno zwrocic: required reviews missing
```

---

## Uzasadnienie blokady TASK-H01

TASK-H01 (wszystkie taski A-G w DONE) jest zablokowany przez TASK-G04, poniewaz:

1. Branch protection wymaga uprawnien administratora – nie mozna zastosowac bez nich.
2. Pozostale taski A-F i G01/G03/G05 sa w statusie DONE.
3. System jest gotowy do zastosowania branch protection – konfiguracja jest tu udokumentowana.

**Akcja wymagana**: Administrator repozytorium powinien zastosowac konfiguracje z tego dokumentu.

---

## Referencje

- [GitHub Branch Protection Rules](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
- [GitHub Rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets)
- `.github/workflows/nightly-quality.yml`
