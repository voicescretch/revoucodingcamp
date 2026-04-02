/**
 * Property 20: Kalkulasi Kembalian yang Benar
 *
 * For any transaction with paid_amount >= total_amount,
 * change_amount = paid_amount - total_amount always.
 *
 * Validates: Requirements 6.8
 */
import { describe, it, expect } from 'vitest';
import * as fc from 'fast-check';

/**
 * Pure function that mirrors the backend change calculation logic.
 */
function calculateChange(paidAmount, totalAmount) {
  if (paidAmount < totalAmount) {
    throw new Error('paid_amount must be >= total_amount');
  }
  return paidAmount - totalAmount;
}

describe('Property 20: Change Calculation', () => {
  it('change_amount = paid_amount - total_amount for any valid payment', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 1, max: 10_000_000 }),    // total_amount
        fc.integer({ min: 0, max: 10_000_000 }),    // overpayment
        (totalAmount, overpayment) => {
          const paidAmount = totalAmount + overpayment;
          const change = calculateChange(paidAmount, totalAmount);
          return change === overpayment;
        }
      ),
      { numRuns: 100 }
    );
  });

  it('change_amount is zero when paid_amount equals total_amount', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 1, max: 10_000_000 }),
        (totalAmount) => {
          const change = calculateChange(totalAmount, totalAmount);
          return change === 0;
        }
      ),
      { numRuns: 100 }
    );
  });

  it('throws when paid_amount < total_amount', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 2, max: 10_000_000 }),   // total_amount
        fc.integer({ min: 1, max: 999_999 }),       // shortfall
        (totalAmount, shortfall) => {
          const paidAmount = totalAmount - shortfall;
          let threw = false;
          try {
            calculateChange(paidAmount, totalAmount);
          } catch {
            threw = true;
          }
          return threw;
        }
      ),
      { numRuns: 100 }
    );
  });
});
