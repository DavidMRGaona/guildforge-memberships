/** Calendar days travel as `YYYY-MM-DD` of the site's display zone; amounts in cents. */
export interface SituationFee {
    amountCents: number;
    currency: string;
    dueOn: string;
    overdue: boolean;
}

export interface SituationPayment {
    amountCents: number;
    currency: string;
    paidOn: string;
}

export interface SituationMembership {
    status: 'pending' | 'active' | 'expired' | 'cancelled';
    periodType: 'calendar_year' | 'academic_year' | 'rolling';
    startsOn: string;
    endsOn: string;
}

/** The profile's member tab (`profileMembership`), null for accounts without a member record. */
export interface MemberSituation {
    memberNumber: string;
    memberType: 'regular' | 'student' | 'senior' | 'honorary' | 'founder';
    status: 'active' | 'inactive' | 'suspended' | 'expelled';
    joinedOn: string;
    permanent: boolean;
    membership: SituationMembership | null;
    pendingFees: SituationFee[];
    lastPayment: SituationPayment | null;
}
