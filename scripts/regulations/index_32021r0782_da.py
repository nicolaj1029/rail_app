#!/usr/bin/env python3
"""
Build a lightweight, local "RAG index" for EU Regulation 2021/782 (DA).

Input:
  webroot/files/Forordninger/CELEX_32021R0782_DA_TXT.pdf

Output:
  config/data/regulations/32021R0782_DA_chunks.json

The output is intentionally simple JSON so the PHP app can do fast keyword search
without extra dependencies.
"""

from __future__ import annotations

import json
import os
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

from PyPDF2 import PdfReader


ARTICLE_RE = re.compile(r"^\s*Artikel\s+(\d+)\b", re.IGNORECASE)


def _norm_ws(s: str) -> str:
    s = s.replace("\u00a0", " ")
    s = re.sub(r"[ \t]+", " ", s)
    # preserve newlines, but collapse excessive blank lines
    s = re.sub(r"\n{3,}", "\n\n", s)
    return s.strip()


def _tokenize_query(q: str) -> List[str]:
    q = q.lower().strip()
    parts = re.split(r"[^a-z0-9æøå]+", q, flags=re.IGNORECASE)
    return [p for p in parts if len(p) >= 3]


@dataclass
class PageText:
    page_no: int  # 1-based
    text: str


def extract_pdf_pages(pdf_path: Path) -> List[PageText]:
    reader = PdfReader(str(pdf_path))
    out: List[PageText] = []
    for i, page in enumerate(reader.pages):
        t = page.extract_text() or ""
        out.append(PageText(page_no=i + 1, text=_norm_ws(t)))
    return out


def split_into_articles(pages: List[PageText]) -> List[Dict[str, Any]]:
    """
    Best-effort split by "Artikel N" headings.
    Returns a list of dicts:
      { article: int, page_from: int, page_to: int, text: str }
    """
    articles: List[Dict[str, Any]] = []
    cur_no: Optional[int] = None
    cur_from: Optional[int] = None
    cur_pages: List[int] = []
    cur_lines: List[str] = []

    def flush() -> None:
        nonlocal cur_no, cur_from, cur_pages, cur_lines
        if cur_no is None or cur_from is None:
            cur_no = None
            cur_from = None
            cur_pages = []
            cur_lines = []
            return
        txt = "\n".join(cur_lines).strip()
        if txt:
            articles.append(
                {
                    "article": cur_no,
                    "page_from": cur_from,
                    "page_to": max(cur_pages) if cur_pages else cur_from,
                    "text": _norm_ws(txt),
                }
            )
        cur_no = None
        cur_from = None
        cur_pages = []
        cur_lines = []

    for p in pages:
        if not p.text:
            continue
        lines = p.text.split("\n")
        for ln in lines:
            m = ARTICLE_RE.match(ln)
            if m:
                # new article begins
                flush()
                cur_no = int(m.group(1))
                cur_from = p.page_no
                cur_pages = [p.page_no]
                cur_lines = [ln]
            else:
                if cur_no is not None:
                    cur_pages.append(p.page_no)
                    cur_lines.append(ln)

    flush()
    return articles


def chunk_text(
    article_no: int,
    text: str,
    page_from: int,
    page_to: int,
    max_chars: int = 1400,
    overlap: int = 200,
) -> List[Dict[str, Any]]:
    """
    Create smaller chunks suitable for search/quoting, keeping article metadata.
    """
    text = _norm_ws(text)
    if not text:
        return []

    chunks: List[Dict[str, Any]] = []
    i = 0
    n = len(text)
    idx = 1
    while i < n:
        j = min(n, i + max_chars)
        # try to break on sentence boundary
        cut = text.rfind(". ", i, j)
        if cut != -1 and cut > i + 200:
            j = cut + 1
        payload = text[i:j].strip()
        if payload:
            chunks.append(
                {
                    "id": f"art{article_no}_p{page_from}_c{idx}",
                    "article": article_no,
                    "chunk_index": idx,
                    "page_from": page_from,
                    "page_to": page_to,
                    "text": payload,
                }
            )
            idx += 1
        if j >= n:
            break
        i = max(0, j - overlap)
    return chunks


def build_index(pdf_path: Path) -> Dict[str, Any]:
    pages = extract_pdf_pages(pdf_path)
    articles = split_into_articles(pages)

    chunks: List[Dict[str, Any]] = []
    for a in articles:
        chunks.extend(
            chunk_text(
                int(a["article"]),
                str(a["text"]),
                int(a["page_from"]),
                int(a["page_to"]),
            )
        )

    return {
        "source": {
            "celex": "32021R0782",
            "lang": "DA",
            "pdf_path": str(pdf_path).replace("\\", "/"),
        },
        "stats": {
            "pages": len(pages),
            "articles_found": len(articles),
            "chunks": len(chunks),
        },
        "chunks": chunks,
    }


def main() -> int:
    root = Path(__file__).resolve().parents[2]
    pdf_path = root / "webroot" / "files" / "Forordninger" / "CELEX_32021R0782_DA_TXT.pdf"
    out_dir = root / "config" / "data" / "regulations"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / "32021R0782_DA_chunks.json"

    if not pdf_path.exists():
        raise SystemExit(f"PDF not found: {pdf_path}")

    index = build_index(pdf_path)
    out_path.write_text(json.dumps(index, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"OK: wrote {out_path} (chunks={index['stats']['chunks']})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
