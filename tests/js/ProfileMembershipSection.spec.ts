import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ProfileMembershipSection from '../../resources/js/components/profile/ProfileMembershipSection.vue';
import type { MemberSituation } from '../../resources/js/types/membership';

function situation(overrides: Partial<MemberSituation> = {}): MemberSituation {
    return {
        memberNumber: '2026-0042',
        memberType: 'student',
        status: 'active',
        joinedOn: '2024-03-05',
        permanent: false,
        membership: {
            status: 'active',
            periodType: 'calendar_year',
            startsOn: '2026-01-01',
            endsOn: '2026-12-31',
        },
        pendingFees: [],
        lastPayment: null,
        ...overrides,
    };
}

describe('ProfileMembershipSection', () => {
    it('renders nothing without a member record', () => {
        const wrapper = mount(ProfileMembershipSection, { props: { profileMembership: null } });

        expect(wrapper.find('[data-testid="member-situation"]').exists()).toBe(false);
    });

    it('shows the record with its labels and calendar days', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: { profileMembership: situation() },
        });

        expect(wrapper.find('[data-testid="member-number"]').text()).toBe('2026-0042');
        expect(wrapper.text()).toContain('Estudiante');
        expect(wrapper.find('[data-testid="member-status"]').text()).toBe('Activo');
        expect(wrapper.text()).toContain('5 de marzo de 2024');

        const membership = wrapper.find('[data-testid="membership"]').text();
        expect(membership).toContain('Activa');
        expect(membership).toContain('Año natural');
        expect(membership).toContain('Del 1 de enero de 2026 al 31 de diciembre de 2026');
    });

    it('says when there is no membership yet and when the type never renews', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: { profileMembership: situation({ membership: null, permanent: true }) },
        });

        const membership = wrapper.find('[data-testid="membership"]').text();
        expect(membership).toContain('aún no te ha registrado ninguna membresía');
        expect(wrapper.find('[data-testid="permanent"]').exists()).toBe(true);
    });

    it('is up to date without pending fees and says no payment is recorded', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: { profileMembership: situation() },
        });

        const fees = wrapper.find('[data-testid="fees"]').text();
        expect(fees).toContain('Estás al día');
        expect(fees).toContain('Todavía no consta ningún pago.');
        expect(wrapper.find('[data-testid="permanent"]').exists()).toBe(false);
    });

    it('lists pending fees with their amount and marks the overdue ones', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: {
                profileMembership: situation({
                    pendingFees: [
                        { amountCents: 3050, currency: 'EUR', dueOn: '2026-02-01', overdue: true },
                        { amountCents: 1500, currency: 'EUR', dueOn: '2026-12-01', overdue: false },
                    ],
                }),
            },
        });

        const items = wrapper.findAll('[data-testid="fees"] li');
        expect(items).toHaveLength(2);
        expect(items[0]?.text()).toMatch(/30,50\s€/u);
        expect(items[0]?.text()).toContain('Vence el 1 de febrero de 2026');
        expect(items[0]?.find('[data-testid="overdue"]').exists()).toBe(true);
        expect(items[1]?.find('[data-testid="overdue"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Estás al día');
    });

    it('shows the last payment', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: {
                profileMembership: situation({
                    lastPayment: { amountCents: 3000, currency: 'EUR', paidOn: '2026-03-10' },
                }),
            },
        });

        expect(wrapper.find('[data-testid="last-payment"]').text()).toMatch(
            /^30,00\s€, pagado el 10 de marzo de 2026$/u
        );
    });

    it('is read only: it points to the association and to its help guide', () => {
        const wrapper = mount(ProfileMembershipSection, {
            props: { profileMembership: situation() },
        });

        expect(wrapper.find('[data-testid="contact"]').attributes('href')).toBe('/nosotros');
        expect(wrapper.find('[data-testid="help-link"]').attributes('data-guide')).toBe(
            'memberships.membership'
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('button').exists()).toBe(false);
    });
});
