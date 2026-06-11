"""Indonesian receipt (struk) parser.

The OCR engine produces raw text; this module turns it into a best-effort
guess of total + line items. UI never auto-saves these — the user always
reviews and edits before persisting (see rule 03).

Currency rules (IDR):
- No decimals in practice. We store integer rupiah.
- EU-style numbers: "." is thousands, "," is decimal.
- Strip the thousands ".", drop anything after ",".
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Iterable

# Keywords that strongly indicate a grand total line.
TOTAL_KEYWORDS = (
    "grand total",
    "total bayar",
    "total belanja",
    "total tagihan",
    "total akhir",
    "total",
)

# Lines that LOOK like totals but aren't the grand total.
NON_TOTAL_KEYWORDS = (
    "subtotal",
    "sub total",
    "ppn",
    "pajak",
    "tax",
    "service",
    "diskon",
    "discount",
    "kembali",
    "kembalian",
    "tunai",
    "cash",
    "qris",
    "debit",
    "kredit",
    "bayar tunai",
)

# Matches a single numeric token in EU style: 25.000 / 12.500,00 / 25000 / 1.250.000
# We grab the longest plausible run.
_NUMBER_RE = re.compile(r"\d{1,3}(?:\.\d{3})+(?:,\d+)?|\d+(?:,\d+)?")


@dataclass
class LineItem:
    name: str
    amount: int


@dataclass
class ParsedReceipt:
    total_guess: int | None = None
    line_items: list[LineItem] = field(default_factory=list)


def parse_amount(token: str) -> int | None:
    """Turn a single numeric token like '25.000' or '12.500,00' into int rupiah.

    Returns None for tokens that don't look numeric. IDR has no cents in
    practice, so any fractional part is truncated (round toward zero).
    """
    token = token.strip()
    if not token:
        return None

    # If there's a decimal comma, keep only the integer part.
    if "," in token:
        whole, _, _frac = token.partition(",")
    else:
        whole = token

    # Strip thousands separator and any stray non-digits.
    digits = re.sub(r"\D", "", whole)
    if not digits:
        return None

    try:
        return int(digits)
    except ValueError:
        return None


def _extract_numbers(line: str) -> list[int]:
    return [
        amt
        for token in _NUMBER_RE.findall(line)
        if (amt := parse_amount(token)) is not None and amt > 0
    ]


def _is_total_line(lowered: str) -> bool:
    if any(bad in lowered for bad in NON_TOTAL_KEYWORDS):
        return False
    return any(k in lowered for k in TOTAL_KEYWORDS)


def _is_skip_line(lowered: str) -> bool:
    return any(bad in lowered for bad in NON_TOTAL_KEYWORDS) or any(
        k in lowered for k in TOTAL_KEYWORDS
    )


def guess_total(lines: Iterable[str]) -> int | None:
    """Pick the most likely grand total in integer rupiah."""
    total_candidates: list[int] = []
    fallback_pool: list[int] = []

    for raw_line in lines:
        line = raw_line.strip()
        if not line:
            continue
        lowered = line.lower()
        numbers = _extract_numbers(line)

        if _is_total_line(lowered) and numbers:
            # Take the last number on the line — receipts put labels left,
            # the amount right, so the trailing number is what we want.
            total_candidates.append(numbers[-1])

        fallback_pool.extend(numbers)

    if total_candidates:
        # Prefer the largest "total"-tagged candidate. Receipts sometimes
        # have both "Subtotal" (filtered out above) and "Total" plus a
        # noisy duplicate; the real grand total tends to be biggest.
        return max(total_candidates)

    return max(fallback_pool) if fallback_pool else None


def guess_line_items(lines: Iterable[str], total: int | None) -> list[LineItem]:
    """Lines with a name on the left and a trailing amount that look like items.

    Best-effort only. We skip total/tax/payment lines and lines whose
    trailing number equals the guessed total.
    """
    items: list[LineItem] = []

    for raw_line in lines:
        line = raw_line.strip()
        if not line:
            continue
        lowered = line.lower()
        if _is_skip_line(lowered):
            continue

        numbers = _extract_numbers(line)
        if not numbers:
            continue

        # Pull the trailing number out of the line as the amount.
        match = list(_NUMBER_RE.finditer(line))
        if not match:
            continue
        last = match[-1]
        amount = parse_amount(last.group(0))
        if amount is None or amount <= 0:
            continue

        # Equal to the grand total? Probably the total line slipped through.
        if total is not None and amount == total:
            continue

        name = line[: last.start()].strip(" .:-\t")
        # Trailing qty x price patterns leave noise like "2 x". Trim.
        name = re.sub(r"\s*\d+\s*[xX×]\s*$", "", name).strip()
        if not name or len(name) < 2:
            continue

        items.append(LineItem(name=name, amount=amount))

    return items


def parse_receipt(raw_text: str) -> ParsedReceipt:
    lines = raw_text.splitlines()
    total = guess_total(lines)
    items = guess_line_items(lines, total)
    return ParsedReceipt(total_guess=total, line_items=items)
