"""Size rules shared by build-ap-catalog.py and build-production-catalog.py."""
from __future__ import annotations

# Noviq stocks 10 mg and larger vials only.
MIN_MG = 10

# Only sold below 10 mg; the client keeps them at their existing sizes.
MIN_MG_EXEMPT = {"aod-9604", "dsip", "ghrp-2", "igf-1-lr3"}


def meets_min_mg(handle: str, variant: dict) -> bool:
    """Variants without amount_mg (blends, sprays, strips, sets) pass: blends count the vial total."""
    amount = variant.get("amount_mg")
    return handle in MIN_MG_EXEMPT or amount is None or amount >= MIN_MG


def apply_min_mg(product: dict) -> dict:
    variants = [v for v in product["variants"] if meets_min_mg(product["handle"], v)]
    if not variants:
        raise RuntimeError(f"{product['handle']} has no variant of {MIN_MG} mg or more")
    product["variants"] = variants
    return product
