import { TestBed } from '@angular/core/testing';

import { Accommodation } from './accommodation';

describe('Accommodation', () => {
  let service: Accommodation;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Accommodation);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
