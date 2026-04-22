import { z } from 'zod';

export const Platform = z.enum(['ios', 'android']);

export const ExternalId = z.string().min(1);

export const CountryCode = z.string();

export const Locale = z.string();

export const DateStr = z
  .string()
  .regex(/^\d{4}-\d{2}-\d{2}$/, 'Must be YYYY-MM-DD');

export const NgramSmall = z.union([z.literal(1), z.literal(2), z.literal(3)]);

export const Page = z.number().int().min(1);
