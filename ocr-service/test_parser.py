"""Lightweight unit tests for the struk parser.

Run with: `pytest -q` (inside the activated venv with `pytest` installed).
These tests don't require Tesseract — they exercise pure-Python parsing.
"""

from __future__ import annotations

from parser import parse_amount, parse_receipt


def test_parse_amount_handles_thousands():
    assert parse_amount("25.000") == 25000
    assert parse_amount("1.250.000") == 1250000
    assert parse_amount("12.500,00") == 12500
    assert parse_amount("Rp 50.000") == 50000


def test_parse_amount_handles_plain_numbers():
    assert parse_amount("0") == 0
    assert parse_amount("90001") == 90001


def test_parse_amount_rejects_garbage():
    assert parse_amount("") is None
    assert parse_amount("abc") is None


def test_guess_total_prefers_total_keyword_over_subtotal():
    receipt = """
    INDOMARET
    Kopi Susu          18.000
    Roti Tawar         12.500
    Subtotal           30.500
    PPN 11%             3.355
    TOTAL              33.855
    Tunai              50.000
    Kembali            16.145
    """
    parsed = parse_receipt(receipt)
    assert parsed.total_guess == 33855


def test_guess_total_handles_total_bayar():
    receipt = """
    Warung Sate Madura
    Sate Ayam 2x       40.000
    Es Teh             8.000
    Total Bayar        48.000
    """
    parsed = parse_receipt(receipt)
    assert parsed.total_guess == 48000


def test_guess_total_falls_back_to_largest_when_no_keyword():
    receipt = """
    Kopi 18000
    Roti 12500
    """
    parsed = parse_receipt(receipt)
    assert parsed.total_guess == 18000


def test_line_items_extracted_and_total_skipped():
    receipt = """
    INDOMARET
    Kopi Susu          18.000
    Roti Tawar         12.500
    TOTAL              30.500
    """
    parsed = parse_receipt(receipt)
    names = [item.name for item in parsed.line_items]
    assert "Kopi Susu" in names
    assert "Roti Tawar" in names
    # The total line itself must not become a line item.
    assert all(item.amount != 30500 for item in parsed.line_items)


def test_returns_none_total_when_empty():
    parsed = parse_receipt("\n\n\n")
    assert parsed.total_guess is None
    assert parsed.line_items == []
